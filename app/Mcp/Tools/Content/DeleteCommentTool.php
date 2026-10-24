<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Models\Content\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 删除评论
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.comment.delete')]
#[Description('删除当前登录用户名下指定 ID 的评论；只能删除自己的评论。')]
#[IsDestructive]
class DeleteCommentTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $comment = Comment::query()
            ->where('id', $arguments['id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $comment) {
            return Response::error('Comment not found or not owned by current user.');
        }

        $comment->delete();

        return Response::structured(['message' => 'Comment deleted successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('评论 ID')->min(1)->required(),
        ];
    }
}
