<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AdminStatus;
use App\Http\Requests\Admin\Admin\AdminChangePasswordRequest;
use App\Http\Requests\Admin\Admin\AdminCreateRequest;
use App\Http\Requests\Admin\Admin\AdminProfileRequest;
use App\Http\Requests\Admin\Admin\AdminUpdateAvatarRequest;
use App\Http\Requests\Admin\Admin\AdminUpdateRequest;
use App\Http\Resources\Admin\AdminResource;
use App\Http\Resources\Admin\LoginHistoryResource;
use App\Models\Admin\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rules\Password;

/**
 * 后台管理员账号管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AdminController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:admins.index')->only(['index', 'show', 'roles', 'loginHistories']);
        $this->middleware('permission:admins.create')->only(['store']);
        $this->middleware('permission:admins.edit')->only(['update', 'toggleStatus', 'assignRoles', 'resetPassword']);
        $this->middleware('permission:admins.delete')->only(['destroy']);
    }

    /**
     * 管理员列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Admin::query()->with('roles');

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', (int) $request->query('status'));
        }

        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($lastLoginIp = $request->query('last_login_ip')) {
            $query->where('last_login_ip', 'like', "%{$lastLoginIp}%");
        }

        if ($startDate = $request->query('last_login_start')) {
            $query->whereDate('last_login_at', '>=', $startDate);
        }

        if ($endDate = $request->query('last_login_end')) {
            $query->whereDate('last_login_at', '<=', $endDate);
        }

        $items = $query->orderByDesc('id')
            ->paginate($perPage);

        return AdminResource::collection($items);
    }

    /**
     * 创建管理员
     */
    public function store(AdminCreateRequest $request): JsonResponse
    {
        $validated = $request->safe()->except(['roles']);
        // 创建管理员
        $admin = Admin::create($validated);
        $data = $request->safe()->only(['roles']);
        // 检查角色设置
        if (! empty($data['roles'])) {
            $admin->syncRoles($data['roles']);
        }

        return response()->json(new AdminResource($admin->load('roles')), 201);
    }

    /**
     * 获取管理员详情
     */
    public function show(Admin $admin): AdminResource
    {
        return new AdminResource($admin->load('roles'));
    }

    /**
     * 更新管理员
     */
    public function update(AdminUpdateRequest $request, Admin $admin): AdminResource
    {
        $validated = $request->safe()->except(['roles', 'password']);

        // 更新基本信息
        $admin->update($validated);
        $data = $request->safe()->only(['password', 'roles']);

        // 检查是否设置了密码
        if (! empty($data['password'])) {
            $admin->resetPassword($data['password']);
        }

        // 检查角色设置
        if (! empty($data['roles'])) {
            $admin->syncRoles($data['roles']);
        }

        return new AdminResource($admin->load('roles'));
    }

    /**
     * 删除管理员
     */
    public function destroy(Admin $admin): JsonResponse
    {
        $currentAdmin = auth('admin')->user();
        if ($currentAdmin && $currentAdmin->id === $admin->id) {
            abort(403, __('admin.admin_cannot_delete_self'));
        }

        if ($admin->hasRole('super_admin')) {
            abort(403, __('admin.admin_cannot_delete_super'));
        }

        $admin->delete();

        return response()->json(status: 204);
    }

    /**
     * 获取管理员已分配角色列表
     */
    public function roles(Admin $admin): JsonResponse
    {
        return response()->json($admin->getRoleNames());
    }

    /**
     * 获取管理员登录历史（分页）
     */
    public function loginHistories(Request $request, Admin $admin): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = $admin->loginHistories();

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('ip', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhere('device', 'like', "%{$keyword}%");
            });
        }

        if ($startDate = $request->query('login_start')) {
            $query->whereDate('login_at', '>=', $startDate);
        }

        if ($endDate = $request->query('login_end')) {
            $query->whereDate('login_at', '<=', $endDate);
        }

        $items = $query->orderByDesc('login_at')->paginate($perPage);

        return LoginHistoryResource::collection($items);
    }

    /**
     * 分配管理员角色
     */
    public function assignRoles(Request $request, Admin $admin): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'max:50', 'exists:roles,name'],
        ]);

        $admin->syncRoles($data['roles']);

        return response()->json($admin->getRoleNames());
    }

    /**
     * 启用/禁用管理员
     */
    public function toggleStatus(Admin $admin): AdminResource
    {
        $currentAdmin = auth('admin')->user();
        if ($currentAdmin && $currentAdmin->id === $admin->id) {
            abort(403, __('admin.admin_cannot_disable_self'));
        }

        $admin->status = $admin->status->isActive() ? AdminStatus::DISABLED : AdminStatus::ACTIVE;
        $admin->save();

        return new AdminResource($admin->fresh()->load('roles'));
    }

    /**
     * 重置管理员密码
     */
    public function resetPassword(Request $request, Admin $admin): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $admin->resetPassword($data['password']);

        return response()->json(status: 204);
    }

    /**
     * 修改当前登录管理员密码
     */
    public function changePassword(AdminChangePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $request->user();

        $data = $request->validated();

        $admin->resetPassword($data['password']);

        return response()->json(status: 204);
    }

    /**
     * 获取当前登录管理员资料
     */
    public function profile(Request $request): AdminResource
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $request->user();

        return new AdminResource($admin->load('roles'));
    }

    /**
     * 修改当前登录管理员头像
     */
    public function updateAvatar(AdminUpdateAvatarRequest $request): AdminResource
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $request->user();

        $upload = $request->handleUpload();

        $admin->update(['avatar' => $upload['file_path']]);

        return new AdminResource($admin->fresh()->load('roles'));
    }

    /**
     * 更新当前登录管理员资料
     */
    public function updateProfile(AdminProfileRequest $request): AdminResource
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $request->user();

        $data = $request->validated();

        $admin->update($data);
        $admin->fresh()->load('roles');

        return new AdminResource($admin);
    }
}
