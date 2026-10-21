<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\Assistant;
use App\Ai\Agents\Finance;
use App\Http\Requests\Admin\Chat\ChatApprovalRequest;
use App\Http\Requests\Admin\Chat\ChatRequest;
use App\Http\Requests\Admin\Chat\FileUploadRequest;
use App\Models\Admin\Admin;
use App\Models\Ai\ChatModel;
use App\Models\System\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AI 聊天控制器
 *
 * 提供管理员与 AI 助手的对话能力：元数据（智能体/模型）、会话列表、消息历史、SSE 流式对话、工具审批。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ChatController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:chat.use');
    }

    /**
     * 聊天元数据：当前管理员可用的智能体与模型列表
     */
    public function meta(Request $request): JsonResponse
    {
        $agents = [
            ['key' => 'assistant', 'name' => '系统管理助手'],
            ['key' => 'finance', 'name' => '财务助手'],
        ];

        return response()->json([
            'agents' => $agents,
            'models' => ChatModel::options(),
            'default_model' => ChatModel::defaultModelId(),
        ]);
    }

    /**
     * 按标识实例化智能体（权限校验由智能体内部处理）
     */
    protected function resolveAgent(?string $key): Assistant|Finance
    {
        if ($key === 'finance') {
            return Finance::make();
        }

        return Assistant::make();
    }

    /**
     * 聊天文件上传
     *
     * @throws ValidationException
     */
    public function uploadFile(FileUploadRequest $request): array
    {
        return $request->handleUpload();
    }

    /**
     * 获取当前管理员的会话列表（分页）
     */
    public function conversations(Request $request): JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        $perPage = per_page($request);

        $conversations = $user->conversations()
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate($perPage);

        return response()->json($conversations);
    }

    /**
     * 获取单个会话的聊天记录
     *
     * 仅允许访问自己参与的会话。
     */
    public function conversation(Request $request, string $conversationId): JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        /** @var Conversation $conversation */
        $conversation = $user->conversations()->find($conversationId);

        if (! $conversation) {
            throw new NotFoundHttpException(__('admin.chat_conversation_not_found'));
        }

        $messages = $conversation->messages()
            ->oldest('created_at')
            ->get()
            ->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toDateTimeString(),
                'tool_calls' => $message->tool_calls,
                'tool_results' => $message->tool_results,
                'approval_state' => $message->approval_state,
                'attachments' => $this->mapMessageAttachments($message->attachments),
            ]);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $messages,
            'created_at' => $conversation->created_at?->toDateTimeString(),
            'updated_at' => $conversation->updated_at?->toDateTimeString(),
        ]);
    }

    /**
     * 发起对话（SSE 流式模式）
     *
     * 直接返回 StreamableAgentResponse，由 Laravel AI 包输出 text/event-stream。
     * 流式过程中可能产生 tool_approval_request 事件，前端应据此展示审批 UI。
     * 支持通过 agent / model 参数指定智能体与模型，attachment_ids 传入图片附件。
     */
    public function stream(ChatRequest $request): mixed
    {
        // 客户端提前断开（用户切换页面/刷新）时仍需完成会话保存：
        // Laravel AI 包在事件流结束后才生成标题并落库会话与消息；
        // 流式生成 + 标题生成可能超过 PHP 默认 30s 时限，这里放宽
        ignore_user_abort(true);
        set_time_limit(300);

        /** @var Admin $user */
        $user = $request->user();
        $prompt = $request->input('prompt');
        $conversationId = $request->input('conversation_id');

        $agent = $this->resolveAgent($request->input('agent'));

        if ($conversationId) {
            $conversation = $user->conversations()->find($conversationId);
            if (! $conversation) {
                throw new NotFoundHttpException(__('admin.chat_conversation_not_found'));
            }
            $agent->continue($conversationId, as: $user);
        } else {
            $agent->forUser($user);
        }

        $attachments = Attachment::query()
            ->whereIn('id', (array) $request->input('attachment_ids', []))
            ->get()
            ->map(fn (Attachment $attachment) => Image::fromStorage($attachment->path, $attachment->disk))
            ->all();

        $response = $agent->stream(
            prompt: $prompt,
            attachments: $attachments,
            model: $request->input('model'),
        );

        return $response;
    }

    /**
     * 处理工具审批决策并续跑（SSE 流式）
     *
     * 前端在收到 tool_approval_request 事件后，用户做出批准/拒绝选择，
     * 携带 conversation_id、approval_id 和 approved 调用本接口，
     * 后端构造 Decisions 续跑暂停的对话，继续以 SSE 输出后续事件。
     */
    public function approve(ChatApprovalRequest $request): mixed
    {
        // 客户端提前断开时仍需完成会话保存；放宽执行时限
        ignore_user_abort(true);
        set_time_limit(300);

        /** @var Admin $user */
        $user = $request->user();
        $conversationId = $request->input('conversation_id');
        $approvalId = $request->input('approval_id');
        $approved = (bool) $request->input('approved');
        $reason = $request->input('reason');

        $conversation = $user->conversations()->find($conversationId);

        if (! $conversation) {
            throw new NotFoundHttpException(__('admin.chat_conversation_not_found'));
        }

        $decision = $approved
            ? Decision::approve()
            : Decision::reject($reason);

        $agent = $this->resolveAgent($request->input('agent'))
            ->continue($conversationId, as: $user);

        return $agent->stream(
            prompt: Decisions::from([$approvalId => $decision]),
            model: $request->input('model'),
        );
    }

    /**
     * 删除会话
     */
    public function destroy(Request $request, string $conversationId): JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        $conversation = $user->conversations()->find($conversationId);

        if (! $conversation) {
            throw new NotFoundHttpException(__('admin.chat_conversation_not_found'));
        }

        $conversation->delete();

        return response()->json(status: 204);
    }

    /**
     * 将消息存储的附件映射为前端可访问的图片 URL 列表
     *
     * @param  array<int, array<string, mixed>>|string|null  $attachments
     * @return array<int, array<string, string|null>>
     */
    protected function mapMessageAttachments(array|string|null $attachments): array
    {
        if (blank($attachments)) {
            return [];
        }

        $decoded = is_string($attachments) ? json_decode($attachments, true) : $attachments;

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(function (array $item): ?array {
                $type = $item['type'] ?? null;
                $path = $item['path'] ?? null;

                return match ($type) {
                    'stored-image' => $path ? [
                        'type' => 'image',
                        'url' => Storage::url($path),
                    ] : null,
                    'remote-image' => isset($item['url']) ? [
                        'type' => 'image',
                        'url' => $item['url'],
                    ] : null,
                    default => null,
                };
            })
            ->filter()
            ->values()
            ->all();
    }
}
