<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Models\Operation\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 前台公开公告接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AnnouncementController extends Controller
{
    /**
     * 公告列表（公开，仅返回已发布的公告）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $items = Announcement::query()
            ->where('status', AnnouncementStatus::PUBLISHED)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return AnnouncementResource::collection($items);
    }

    /**
     * 公告详情（公开，仅返回已发布的公告）
     */
    public function show(int $id): AnnouncementResource
    {
        $announcement = Announcement::query()
            ->where('status', AnnouncementStatus::PUBLISHED)
            ->findOrFail($id);

        return new AnnouncementResource($announcement);
    }
}
