<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Common;

use App\Models\System\Area;
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
 * 获取地区列表（按父级 ID 拉取下一级）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('common.area.list')]
#[Description('获取地区列表。不传 area_id 返回顶级（省级）地区；传入 area_id 返回该地区的下一级子地区。')]
#[IsReadOnly]
#[IsIdempotent]
class ListAreasTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'area_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Area::query()
            ->select(['id', 'name'])
            ->orderBy('sort')
            ->orderBy('id');

        if (! empty($arguments['area_id'])) {
            $query->where('parent_id', (int) $arguments['area_id']);
        } else {
            $query->whereNull('parent_id');
        }

        return Response::structured(['items' => $query->get()->toArray()]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'area_id' => $schema->integer()->description('可选，父级地区 ID；不传返回省级列表')->min(1),
        ];
    }
}
