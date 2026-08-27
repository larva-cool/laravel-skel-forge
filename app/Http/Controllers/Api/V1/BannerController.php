<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BannerPublicResource;
use App\Models\Operation\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 前台公开 Banner / 轮播图接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerController extends Controller
{
    /**
     * 获取指定广告位下当前生效的 Banner 列表（公开，无需认证）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'position' => ['required', 'string', 'max:64'],
        ]);

        $position = $request->query('position');
        $now = now();

        $items = Banner::query()
            ->where('position', $position)
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

        return BannerPublicResource::collection($items);
    }
}
