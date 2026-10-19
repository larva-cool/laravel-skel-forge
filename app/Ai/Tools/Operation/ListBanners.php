<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Operation;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Operation\Banner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Banner 列表查询工具。
 *
 * 支持按广告位、标题关键字、启用状态筛选，返回分页 Banner 数据。只读操作。
 */
class ListBanners implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'banners.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询 Banner/轮播图列表。支持按广告位标识、标题关键字、启用状态筛选，以分页形式返回 Banner 信息（含图片、链接、排序、生效时间等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'position' => $schema->string()
                ->max(50)
                ->description('广告位标识筛选（对应字典 banner_positions 的 value）'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配 Banner 标题'),
            'status' => $schema->boolean()
                ->description('启用状态筛选：true=仅启用，false=仅禁用。不传则查询全部'),
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

        $query = Banner::query();

        if ($position = $request->string('position')->toString()) {
            $query->where('position', $position);
        }

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Banner $banner) => [
            'id' => $banner->id,
            'position' => $banner->position,
            'title' => $banner->title,
            'image' => $banner->image,
            'link_type' => $banner->link_type->value,
            'link_type_label' => $banner->link_type->label(),
            'link_value' => $banner->link_value,
            'sort' => $banner->sort,
            'status' => $banner->status,
            'start_at' => $banner->start_at?->toDateTimeString(),
            'end_at' => $banner->end_at?->toDateTimeString(),
            'created_at' => $banner->created_at?->toDateTimeString(),
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
