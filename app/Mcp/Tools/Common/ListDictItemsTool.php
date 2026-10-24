<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Common;

use App\Http\Resources\Api\V1\PublicDictItemResource;
use App\Models\System\Dict;
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
 * 获取公开字典项
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('common.dict_item.list')]
#[Description('根据字典编码获取公开字典项列表（如反馈类型等白名单内的字典）。')]
#[IsReadOnly]
#[IsIdempotent]
class ListDictItemsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $items = Dict::getActiveItemsByCode($arguments['code']);

        return Response::structured([
            'items' => PublicDictItemResource::collection($items)->resolve(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('字典编码')->max(64)->required(),
        ];
    }
}
