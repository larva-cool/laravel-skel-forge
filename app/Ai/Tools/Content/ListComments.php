<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Content;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Content\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 评论列表查询工具。
 *
 * 支持按审核状态、源类型、内容关键字、用户筛选，返回分页评论数据。只读操作。
 */
class ListComments implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'comments.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户评论列表。支持按审核状态（待审核/需复审/已通过/已驳回）、源类型、评论内容关键字、评论用户 ID 筛选，以分页形式返回评论信息（含点赞数、回复数、发布者、IP 等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['pending', 'review', 'approved', 'rejected'])
                ->description('审核状态筛选：pending=待审核，review=需复审，approved=已通过，rejected=已驳回'),
            'source_type' => $schema->string()
                ->max(50)
                ->description('评论源类型筛选（多态类型，如 comment 表示评论的回复）'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配评论内容'),
            'user_id' => $schema->integer()
                ->description('精确按评论用户 ID 筛选'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $query = Comment::query()->with('user');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($sourceType = $request->string('source_type')->toString()) {
            $query->where('source_type', $sourceType);
        }

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('content', 'like', "%{$keyword}%");
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Comment $comment) => [
            'id' => $comment->id,
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'username' => $comment->user->username,
                'name' => $comment->user->name,
            ] : null,
            'source_type' => $comment->source_type,
            'source_id' => $comment->source_id,
            'content' => $comment->content,
            'status' => $comment->status->value,
            'status_label' => $comment->status->label(),
            'is_top' => $comment->is_top,
            'like_count' => $comment->like_count,
            'comment_count' => $comment->comment_count,
            'ip_address' => $comment->ip_address,
            'created_at' => $comment->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
