<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\System\Dict;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 字典类型与字典数据查询工具。
 *
 * 支持按名称/编码关键字、启用状态筛选，返回字典类型及其字典项。只读操作。
 */
class ListDicts implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'dicts.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询系统字典类型及其字典数据（字典项）。支持按字典名称/编码关键字、启用状态筛选；当需要确认某个字典编码（如 feedback_type、report_category、banner_positions）下有哪些可用取值时使用。只读操作。';
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
                ->description('模糊匹配字典名称或编码，查询具体某个字典时建议直接传完整编码'),
            'status' => $schema->integer()
                ->enum([0, 1])
                ->description('状态筛选：0=禁用，1=启用'),
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

        $query = Dict::query()->with(['dictItems' => fn ($q) => $q->orderBy('sort')]);

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->integer('status'));
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderBy('sort')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Dict $dict) => [
            'id' => $dict->id,
            'name' => $dict->name,
            'code' => $dict->code,
            'status' => $dict->status,
            'remark' => $dict->remark,
            'items' => $dict->dictItems->map(fn ($item) => [
                'label' => $item->label,
                'value' => $item->value,
                'status' => $item->status,
            ])->values(),
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
