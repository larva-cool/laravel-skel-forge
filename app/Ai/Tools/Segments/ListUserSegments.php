<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Segments;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\UserSegment\Segment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户分群列表查询工具。
 *
 * 支持按名称/编码关键字、分群类型、启用状态筛选，返回分页分群数据。只读操作。
 */
class ListUserSegments implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'segments.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户分群列表。支持按名称/编码关键字、分群类型（手动/规则）、启用状态筛选，以分页形式返回分群信息（含成员数、规则最近同步时间与匹配用户数等）。只读操作。';
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
                ->description('模糊匹配分群名称或编码'),
            'type' => $schema->integer()
                ->enum([1, 2])
                ->description('分群类型筛选：1=手动分群，2=规则分群'),
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

        $query = Segment::query()->withCount('memberships');

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->integer('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Segment $segment) => [
            'id' => $segment->id,
            'code' => $segment->code,
            'name' => $segment->name,
            'type' => $segment->type->value,
            'type_label' => $segment->type->label(),
            'group' => $segment->group,
            'status' => $segment->status,
            'member_count' => $segment->memberships_count,
            'last_synced_at' => $segment->last_synced_at?->toDateTimeString(),
            'last_synced_user_count' => $segment->last_synced_user_count,
            'created_at' => $segment->created_at?->toDateTimeString(),
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
