<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Operation;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Operation\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 公告列表查询工具。
 *
 * 支持按标题关键字、状态筛选，返回分页公告摘要数据。只读操作。
 */
class ListAnnouncements implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'announcements.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询平台公告列表。支持按标题关键字、状态（草稿/已发布/已归档）筛选，以分页形式返回公告摘要信息（含发布时间、排序权重等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配公告标题'),
            'status' => $schema->string()
                ->enum(['draft', 'published', 'archived'])
                ->description('状态筛选：draft=草稿，published=已发布，archived=已归档'),
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

        $query = Announcement::query();

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Announcement $announcement) => [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'cover_image' => $announcement->cover_image,
            'status' => $announcement->status->value,
            'status_label' => $announcement->status->label(),
            'sort' => $announcement->sort,
            'published_at' => $announcement->published_at?->toDateTimeString(),
            'created_at' => $announcement->created_at?->toDateTimeString(),
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
