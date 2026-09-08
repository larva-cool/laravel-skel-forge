<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Http\Requests\Admin\Announcement\AnnouncementIndexRequest;
use App\Http\Requests\Admin\Announcement\AnnouncementSaveRequest;
use App\Http\Resources\Admin\AnnouncementResource;
use App\Models\Operation\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台公告管理控制器（运营管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AnnouncementController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:announcements.index')->only(['index', 'show']);
        $this->middleware('permission:announcements.create')->only(['store']);
        $this->middleware('permission:announcements.edit')->only(['update', 'toggleStatus']);
        $this->middleware('permission:announcements.delete')->only(['destroy']);
    }

    /**
     * 公告列表（分页，支持关键词/状态筛选）
     */
    public function index(AnnouncementIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Announcement::query();

        // 关键词搜索：标题
        if ($keyword = $request->query('keyword')) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        // 状态筛选
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return AnnouncementResource::collection($items);
    }

    /**
     * 创建公告
     */
    public function store(AnnouncementSaveRequest $request): AnnouncementResource
    {
        $data = $request->validated();
        $data['admin_id'] = $request->user('admin')->id;

        $announcement = Announcement::query()->create($data);

        return new AnnouncementResource($announcement);
    }

    /**
     * 公告详情
     */
    public function show(int $id): AnnouncementResource
    {
        $announcement = Announcement::query()->with('admin')->findOrFail($id);

        return new AnnouncementResource($announcement);
    }

    /**
     * 更新公告
     */
    public function update(AnnouncementSaveRequest $request, int $id): AnnouncementResource
    {
        $announcement = Announcement::query()->findOrFail($id);
        $announcement->update($request->validated());

        return new AnnouncementResource($announcement->fresh());
    }

    /**
     * 切换公告状态（草稿→发布 / 发布→归档 / 归档→发布）
     */
    public function toggleStatus(int $id): AnnouncementResource
    {
        $announcement = Announcement::query()->findOrFail($id);

        $newStatus = match ($announcement->status) {
            AnnouncementStatus::DRAFT => AnnouncementStatus::PUBLISHED,
            AnnouncementStatus::PUBLISHED => AnnouncementStatus::ARCHIVED,
            AnnouncementStatus::ARCHIVED => AnnouncementStatus::PUBLISHED,
        };

        $announcement->update([
            'status' => $newStatus,
            'published_at' => $newStatus === AnnouncementStatus::PUBLISHED && $announcement->published_at === null
                ? now()
                : $announcement->published_at,
        ]);

        return new AnnouncementResource($announcement->fresh());
    }

    /**
     * 删除公告
     */
    public function destroy(int $id): JsonResponse
    {
        $announcement = Announcement::query()->findOrFail($id);
        $announcement->delete();

        return response()->json(status: 204);
    }
}
