<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Enums\CacheKey;
use App\Models\System\Area;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 地区数据查询工具。
 *
 * 支持查询顶级地区（省份）列表、指定父级下的直属子地区，或整棵地区树。
 * 使用缓存避免重复递归查询。只读操作。
 */
class GetAreas implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询系统中的地区数据（省/市/区）。支持查询顶级省份列表、指定父级地区下的直属子地区，或一次性获取完整地区树。用于地址选择、地区编码核对等场景。只读操作，结果会被缓存。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()
                ->enum(['roots', 'children', 'tree'])
                ->default('roots')
                ->description('查询模式：roots=顶级省份列表（默认），children=指定 parent_id 下的直属子地区，tree=完整递归地区树'),
            'parent_id' => $schema->integer()
                ->description('父级地区 ID，仅 mode=children 时需要。例如查询某省下的城市'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $mode = $request->string('mode')->toString() ?: 'roots';

        return match ($mode) {
            'children' => $this->children($request->integer('parent_id')),
            'tree' => $this->tree(),
            default => $this->roots(),
        };
    }

    /**
     * 顶级地区列表（带缓存）。
     */
    protected function roots(): string
    {
        $areas = Cache::remember(
            CacheKey::key(CacheKey::AREA_TREE, 'root'),
            now()->addHours(2),
            fn () => Area::query()->root()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'area_code', 'city_code'])
        );

        return json_encode([
            'mode' => 'roots',
            'count' => $areas->count(),
            'items' => $areas->map(fn (Area $area) => $this->formatArea($area))->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 指定父级下的直属子地区。
     */
    protected function children(?int $parentId): string
    {
        if (! $parentId) {
            return '错误：mode=children 时必须提供 parent_id。';
        }

        $parent = Area::query()->find($parentId, ['id', 'name']);
        if (! $parent) {
            return "未找到 ID 为 {$parentId} 的地区。";
        }

        $areas = Cache::remember(
            CacheKey::key(CacheKey::AREA_TREE, $parentId),
            now()->addHours(2),
            fn () => Area::query()->where('parent_id', $parentId)->orderBy('sort')->orderBy('id')->get(['id', 'parent_id', 'name', 'area_code', 'city_code'])
        );

        return json_encode([
            'mode' => 'children',
            'parent' => ['id' => $parent->id, 'name' => $parent->name],
            'count' => $areas->count(),
            'items' => $areas->map(fn (Area $area) => $this->formatArea($area))->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 完整递归地区树。
     */
    protected function tree(): string
    {
        $tree = Area::tree();

        return json_encode([
            'mode' => 'tree',
            'count' => $tree->count(),
            'items' => $tree->map(fn (Area $area) => $this->formatTree($area))->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 格式化地区摘要（不含子级）。
     *
     * @return array<string, mixed>
     */
    protected function formatArea(Area $area): array
    {
        return [
            'id' => $area->id,
            'parent_id' => $area->parent_id,
            'name' => $area->name,
            'area_code' => $area->area_code,
            'city_code' => $area->city_code,
        ];
    }

    /**
     * 递归格式化地区树节点。
     *
     * @return array<string, mixed>
     */
    protected function formatTree(Area $area): array
    {
        return [
            'id' => $area->id,
            'name' => $area->name,
            'area_code' => $area->area_code,
            'city_code' => $area->city_code,
            'children' => $area->children->map(fn (Area $child) => $this->formatTree($child))->all(),
        ];
    }
}
