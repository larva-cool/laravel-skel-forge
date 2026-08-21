<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Role\RoleSaveRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Admin\AdminMenu;
use App\Models\System\Permission;
use App\Models\System\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台角色管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RoleController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:roles.index')->only(['index', 'show', 'permissions', 'allPermissions']);
        $this->middleware('permission:roles.create')->only(['store']);
        $this->middleware('permission:roles.edit')->only(['update', 'assignPermissions']);
        $this->middleware('permission:roles.delete')->only(['destroy']);
    }

    /**
     * 角色列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Role::query()
            ->where('guard_name', AdminMenu::GUARD_NAME);

        if ($name = $request->query('role_name')) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($displayName = $request->query('display_name')) {
            $query->where('display_name', 'like', "%{$displayName}%");
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return RoleResource::collection($items);
    }

    /**
     * 创建角色
     */
    public function store(RoleSaveRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'display_name' => $request->validated('display_name'),
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        return response()->json(new RoleResource($role), 201);
    }

    /**
     * 获取角色详情
     */
    public function show(Role $role): RoleResource
    {
        return new RoleResource($role);
    }

    /**
     * 更新角色
     */
    public function update(RoleSaveRequest $request, Role $role): RoleResource
    {
        if ($role->name === 'super_admin') {
            abort(403, __('admin.role_cannot_modify_super'));
        }

        $role->update([
            'name' => $request->validated('name'),
            'display_name' => $request->validated('display_name'),
        ]);

        return new RoleResource($role);
    }

    /**
     * 删除角色
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'super_admin') {
            abort(403, __('admin.role_cannot_delete_super'));
        }

        if ($role->users()->count() > 0) {
            abort(400, __('admin.role_in_use'));
        }

        $role->delete();

        return response()->json(status: 204);
    }

    /**
     * 获取角色已分配的权限 ID 列表
     */
    public function permissions(Role $role): JsonResponse
    {
        return response()->json($role->permissions()->pluck('id'));
    }

    /**
     * 分配角色权限
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'super_admin') {
            abort(403, __('admin.role_cannot_modify_super'));
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $validPermissionIds = Permission::filterByGuard($data['permissions'], AdminMenu::GUARD_NAME);

        $role->syncPermissions($validPermissionIds);

        return response()->json($role->permissions()->pluck('id'));
    }

    /**
     * 获取所有权限列表（用于权限分配）
     */
    public function allPermissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', AdminMenu::GUARD_NAME)
            ->orderBy('id')
            ->get(['id', 'name', 'display_name']);

        return response()->json($permissions);
    }
}
