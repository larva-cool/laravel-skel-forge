<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Menu\MenuSaveRequest;
use App\Http\Resources\Admin\MenuResource;
use App\Models\Admin\AdminMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台菜单管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MenuController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:menus.index')->only(['index', 'show']);
        $this->middleware('permission:menus.create')->only(['store']);
        $this->middleware('permission:menus.edit')->only(['update']);
        $this->middleware('permission:menus.delete')->only(['destroy']);
    }

    /**
     * 菜单列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = AdminMenu::with(['children'])
            ->root()
            ->ordered()
            ->orderBy('id')
            ->get();

        return MenuResource::collection($items);
    }

    /**
     * 创建菜单
     */
    public function store(MenuSaveRequest $request): JsonResponse
    {
        $menu = AdminMenu::create($request->validated());

        return response()->json(new MenuResource($menu), 201);
    }

    /**
     * 获取菜单详情
     */
    public function show(AdminMenu $menu): MenuResource
    {
        return new MenuResource($menu);
    }

    /**
     * 更新菜单
     */
    public function update(MenuSaveRequest $request, AdminMenu $menu): MenuResource
    {
        $parentId = $request->validated('parent_id');
        if ($parentId !== null && $this->isDescendantOrSelf($menu, (int) $parentId)) {
            abort(422, __('admin.menu_invalid_parent'));
        }

        $menu->update($request->validated());

        return new MenuResource($menu);
    }

    /**
     * 删除菜单
     */
    public function destroy(AdminMenu $menu): JsonResponse
    {
        if ($menu->children()->exists()) {
            abort(400, __('admin.menu_has_children'));
        }

        $menu->delete();

        return response()->json(status: 204);
    }

    /**
     * 判断目标菜单是否为当前菜单的自身或后代
     */
    protected function isDescendantOrSelf(AdminMenu $menu, int $targetId): bool
    {
        if ($menu->id === $targetId) {
            return true;
        }

        // 预加载所有后代（利用 children 关系定义中的递归 with('children')）
        if (! $menu->relationLoaded('children')) {
            $menu->load('children');
        }

        foreach ($menu->children as $child) {
            if ($this->isDescendantOrSelf($child, $targetId)) {
                return true;
            }
        }

        return false;
    }
}
