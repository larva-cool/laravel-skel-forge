<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Ai\AiCopyRequest;
use App\Http\Requests\Admin\Ai\AiImageRequest;
use App\Http\Requests\Admin\Banner\BannerIndexRequest;
use App\Http\Requests\Admin\Banner\BannerSaveRequest;
use App\Http\Requests\Admin\Banner\BannerSortRequest;
use App\Http\Resources\Admin\BannerResource;
use App\Models\Operation\Banner;
use App\Models\System\Dict;
use App\Services\Ai\ContentAssistService;
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
        $this->middleware('permission:banners.index')->only(['index', 'show', 'positions']);
        $this->middleware('permission:banners.create')->only(['store', 'generateCopy', 'generateImage']);
        $this->middleware('permission:banners.edit')->only(['update', 'toggleStatus', 'sort']);
        $this->middleware('permission:banners.delete')->only(['destroy']);
    }

    /**
     * AI 生成 Banner 文案（标题 + 绘图提示词，仅生成不落库）
     */
    public function generateCopy(AiCopyRequest $request, ContentAssistService $service): JsonResponse
    {
        // AI 生成耗时较长，放宽执行时限
        ignore_user_abort(true);
        set_time_limit(120);

        return response()->json($service->generateBannerCopy($request->string('brief')->toString()));
    }

    /**
     * AI 生成 Banner 配图（Seedream 绘图并落盘，返回访问 URL）
     */
    public function generateImage(AiImageRequest $request, ContentAssistService $service): JsonResponse
    {
        // Seedream 绘图耗时较长，放宽执行时限
        ignore_user_abort(true);
        set_time_limit(120);

        return response()->json($service->generateBannerImage($request->string('prompt')->toString()));
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

    /**
     * 获取所有可用广告位选项（基于字典 banner_positions）
     *
     * 用于后台新增/编辑 Banner 时的下拉选择。
     */
    public function positions(): JsonResponse
    {
        $items = Dict::getActiveItemsByCode('banner_positions');

        $data = $items->map(fn ($item) => [
            'label' => $item->label,
            'value' => $item->value,
        ])->values();

        return response()->json($data);
    }
}
