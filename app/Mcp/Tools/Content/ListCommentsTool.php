<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Enums\ReviewStatus;
use App\Http\Resources\Api\V1\CommentResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use App\Models\Content\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取评论列表（仅已审核通过的公开评论）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.comment.list')]
#[Description('获取已审核通过的公开评论列表（分页），可按 source_type、source_id 过滤指定对象下的评论。')]
#[IsReadOnly]
#[IsIdempotent]
class ListCommentsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'source_type' => ['sometimes', 'string', 'max:64'],
            'source_id' => ['sometimes', 'integer', 'min:1'],
        ]));

        $query = Comment::query()
            ->where('status', ReviewStatus::APPROVED)
            ->orderByDesc('id');

        if (! empty($arguments['source_type'])) {
            $query->where('source_type', $arguments['source_type']);
        }

        if (! empty($arguments['source_id'])) {
            $query->where('source_id', $arguments['source_id']);
        }

        $paginator = $query->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, CommentResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'source_type' => $schema->string()->description('可选，评论对象的来源类型（morph 别名）')->max(64),
            'source_id' => $schema->integer()->description('可选，评论对象的来源 ID')->min(1),
        ]);
    }
}
