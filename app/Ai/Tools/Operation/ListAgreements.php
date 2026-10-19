<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Operation;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Operation\Agreement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户协议列表查询工具。
 *
 * 支持按标题关键字、协议类别、状态筛选，返回分页协议摘要数据。只读操作。
 */
class ListAgreements implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'agreements.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户协议列表。支持按标题关键字、协议类别（如 user、privacy 等字典值）、状态（草稿/已发布/已归档）筛选，以分页形式返回协议摘要信息（含版本号、生效时间等；正文为富文本，列表仅返回截断摘要）。只读操作。';
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
                ->description('模糊匹配协议标题'),
            'category' => $schema->string()
                ->max(50)
                ->description('协议类别筛选（字典值，如 user=用户协议、privacy=隐私政策）'),
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

        $query = Agreement::query();

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Agreement $agreement) => [
            'id' => $agreement->id,
            'title' => $agreement->title,
            'category' => $agreement->category,
            'version' => $agreement->version,
            'status' => $agreement->status->value,
            'status_label' => $agreement->status->label(),
            'content_excerpt' => mb_substr(strip_tags($agreement->content ?? ''), 0, 200),
            'effective_at' => $agreement->effective_at?->toDateTimeString(),
            'sort' => $agreement->sort,
            'updated_at' => $agreement->updated_at?->toDateTimeString(),
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
