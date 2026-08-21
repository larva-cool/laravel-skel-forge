<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Area\AreaSaveRequest;
use App\Http\Resources\Admin\AreaResource;
use App\Models\System\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台地区管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AreaController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:areas.index')->only(['index', 'show']);
        $this->middleware('permission:areas.create')->only(['store']);
        $this->middleware('permission:areas.edit')->only(['update']);
        $this->middleware('permission:areas.delete')->only(['destroy']);
    }

    /**
     * 获取地区树形结构
     */
    public function index(): AnonymousResourceCollection
    {
        $areas = Area::tree();

        return AreaResource::collection($areas);
    }

    /**
     * 创建地区
     */
    public function store(AreaSaveRequest $request): JsonResponse
    {
        $area = Area::create($request->validated());

        return response()->json(new AreaResource($area), 201);
    }

    /**
     * 获取地区详情
     */
    public function show(int $id): AreaResource
    {
        return new AreaResource(Area::findOrFail($id));
    }

    /**
     * 更新地区
     */
    public function update(AreaSaveRequest $request, int $id): AreaResource
    {
        $area = Area::findOrFail($id);

        $parentId = $request->validated('parent_id');
        if ($parentId !== null && $this->isDescendantOrSelf($area, $parentId)) {
            abort(422, __('admin.area_invalid_parent'));
        }

        $area->update($request->validated());

        return new AreaResource($area);
    }

    /**
     * 删除地区
     */
    public function destroy(int $id): JsonResponse
    {
        $area = Area::findOrFail($id);

        if ($area->children()->exists()) {
            abort(400, __('admin.area_has_children'));
        }

        $area->delete();

        return response()->json(status: 204);
    }

    /**
     * 判断目标地区是否为当前地区的自身或后代
     */
    protected function isDescendantOrSelf(Area $area, int $targetId): bool
    {
        if ($area->id === $targetId) {
            return true;
        }

        return $area->children->contains(
            fn (Area $child): bool => $this->isDescendantOrSelf($child, $targetId)
        );
    }
}
