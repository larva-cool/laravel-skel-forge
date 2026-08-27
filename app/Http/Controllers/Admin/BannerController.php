<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Banner\BannerIndexRequest;
use App\Http\Requests\Admin\Banner\BannerSaveRequest;
use App\Http\Requests\Admin\Banner\BannerSortRequest;
use App\Http\Resources\Admin\BannerResource;
use App\Models\Operation\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台 Banner / 轮播图管理控制器（运营管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:banners.index')->only(['index', 'show']);
        $this->middleware('permission:banners.create')->only(['store']);
        $this->middleware('permission:banners.edit')->only(['update', 'toggleStatus', 'sort']);
        $this->middleware('permission:banners.delete')->only(['destroy']);
    }

    /**
     * Banner 列表（分页，支持广告位/关键词/状态筛选）
     */
    public function index(BannerIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Banner::query();

        // 按广告位筛选
        if ($position = $request->query('position')) {
            $query->where('position', $position);
        }

        // 关键词搜索：标题
        if ($keyword = $request->query('keyword')) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        // 状态筛选
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $items = $query->orderBy('sort')->orderByDesc('id')->paginate($perPage);

        return BannerResource::collection($items);
    }

    /**
     * 创建 Banner
     */
    public function store(BannerSaveRequest $request): BannerResource
    {
        $banner = Banner::query()->create($request->validated());

        return new BannerResource($banner);
    }

    /**
     * Banner 详情
     */
    public function show(int $id): BannerResource
    {
        $banner = Banner::query()->findOrFail($id);

        return new BannerResource($banner);
    }

    /**
     * 更新 Banner
     */
    public function update(BannerSaveRequest $request, int $id): BannerResource
    {
        $banner = Banner::query()->findOrFail($id);
        $banner->update($request->validated());

        return new BannerResource($banner);
    }

    /**
     * 切换 Banner 启用/禁用状态
     */
    public function toggleStatus(int $id): BannerResource
    {
        $banner = Banner::query()->findOrFail($id);
        $banner->update([
            'status' => ! $banner->status,
        ]);

        return new BannerResource($banner);
    }

    /**
     * 拖拽批量排序（同广告位内）
     */
    public function sort(BannerSortRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');

        foreach ($ids as $index => $id) {
            Banner::query()->where('id', $id)->update([
                'sort' => $index,
            ]);
        }

        return response()->json(status: 204);
    }

    /**
     * 删除 Banner
     */
    public function destroy(int $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $banner->delete();

        return response()->json(status: 204);
    }
}
