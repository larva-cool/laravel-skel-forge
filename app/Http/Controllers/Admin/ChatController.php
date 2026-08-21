<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\Assistant;
use App\Http\Requests\Admin\ChatApprovalRequest;
use App\Http\Requests\Admin\ChatRequest;
use App\Models\Admin\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AI 聊天控制器
 *
 * 提供管理员与 AI 助手的对话能力：会话列表、消息历史、SSE 流式对话、工具审批。
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
            throw new NotFoundHttpException('会话不存在');
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
     */
    public function stream(ChatRequest $request): mixed
    {
        /** @var Admin $user */
        $user = $request->user();
        $prompt = $request->input('prompt');
        $conversationId = $request->input('conversation_id');

        $agent = Assistant::make();

        if ($conversationId) {
            $conversation = $user->conversations()->find($conversationId);
            if (! $conversation) {
                throw new NotFoundHttpException('会话不存在');
            }
            $agent->continue($conversationId, as: $user);
        } else {
            $agent->forUser($user);
        }

        return $agent->stream(prompt: $prompt);
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
        /** @var Admin $user */
        $user = $request->user();
        $conversationId = $request->input('conversation_id');
        $approvalId = $request->input('approval_id');
        $approved = (bool) $request->input('approved');
        $reason = $request->input('reason');

        $conversation = $user->conversations()->find($conversationId);
        if (! $conversation) {
            throw new NotFoundHttpException('会话不存在');
        }

        $decision = $approved
            ? Decision::approve()
            : Decision::reject($reason);

        $agent = Assistant::make()->continue($conversationId, as: $user);

        return $agent->stream(Decisions::from([
            $approvalId => $decision,
        ]));
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
            throw new NotFoundHttpException('会话不存在');
        }

        $conversation->delete();

        return response()->json(status: 204);
    }
}
