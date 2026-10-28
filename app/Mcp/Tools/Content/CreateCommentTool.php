<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Enums\ReviewStatus;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Content\Comment;
use App\Services\SensitiveWordService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 发布评论
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.comment.create')]
#[Description('在当前登录用户名下发布一条评论，支持回复父评论与艾特用户；内容经过敏感词过滤，命中 review 级别时进入待审核。')]
#[IsDestructive]
class CreateCommentTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'source_type' => ['required', 'string', 'max:64'],
            'source_id' => ['required', 'integer', 'min:1'],
            'content' => ['required', 'string', 'min:1', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'mentioned_users' => ['nullable', 'array', 'max:10'],
            'mentioned_users.*' => ['integer', 'min:1'],
        ]);

        // 敏感词处理
        $service = app(SensitiveWordService::class);
        $result = $service->detect((string) $arguments['content']);
        $arguments['content'] = $result->filtered;
        $arguments['status'] = $result->maxLevel === \App\Enums\SensitiveLevel::REVIEW
            ? ReviewStatus::PENDING->value
            : ReviewStatus::APPROVED->value;

        if ($result->hits !== []) {
            try {
                $service->incrementHitCount($result->hitWords());
            } catch (\Throwable) {
            }
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $comment = Comment::create([
            'user_id' => $user->id,
            'source_type' => $arguments['source_type'],
            'source_id' => $arguments['source_id'],
            'content' => $arguments['content'],
            'status' => $arguments['status'],
            'mentioned_users' => $arguments['mentioned_users'] ?? null,
            'ip_address' => '', // MCP 无 HTTP IP
        ]);

        // 回复父评论
        if (! empty($arguments['parent_id'])) {
            $parent = Comment::find($arguments['parent_id']);
            if ($parent) {
                $comment->source_type = Comment::class;
                $comment->source_id = $parent->id;
                $comment->save();
            }
        }

        return Response::structured((new CommentResource($comment))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->description('评论目标类型（morph 别名），如 post、video')->max(64)->required(),
            'source_id' => $schema->integer()->description('评论目标 ID')->min(1)->required(),
            'content' => $schema->string()->description('评论内容，1-1000 字')->min(1)->max(1000)->required(),
            'parent_id' => $schema->integer()->description('可选，父评论 ID（用于回复）')->min(1),
            'mentioned_users' => $schema->array()->description('可选，艾特用户 ID 列表，最多 10 个'),
        ];
    }
}
