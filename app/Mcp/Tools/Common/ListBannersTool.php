<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Common;

use App\Http\Resources\Api\V1\BannerPublicResource;
use App\Models\Operation\Banner;
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
 * 获取指定广告位下当前生效的 Banner 列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('common.banner.list')]
#[Description('获取指定广告位下当前生效的 Banner 轮播图列表，按排序字段升序返回。')]
#[IsReadOnly]
#[IsIdempotent]
class ListBannersTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'position' => ['required', 'string', 'max:64'],
        ]);

        $now = now();

        $items = Banner::query()
            ->where('position', $arguments['position'])
            ->where('status', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();

        return Response::structured([
            'items' => BannerPublicResource::collection($items)->resolve(),
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
            'position' => $schema->string()->description('广告位编码，如 home_top、home_banner、launch、user_center、discover')->required(),
        ];
    }
}
