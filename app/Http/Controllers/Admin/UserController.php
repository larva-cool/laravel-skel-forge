<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CoinType;
use App\Enums\PointType;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientCoinsException;
use App\Exceptions\InsufficientPointsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\UserAdjustBalanceRequest;
use App\Http\Requests\Admin\User\UserExtendVipRequest;
use App\Http\Requests\Admin\User\UserUpdateRequest;
use App\Http\Resources\Admin\CoinTradeResource;
use App\Http\Resources\Admin\LoginHistoryResource;
use App\Http\Resources\Admin\PointTradeResource;
use App\Http\Resources\Admin\SocialResource;
use App\Http\Resources\Admin\UserDetailResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Support\CoinHelper;
use App\Support\PointHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rules\Password;

/**
 * 后台用户管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserController extends Controller
{
    use \App\Exports\HandlesExports;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:users.index')->only(['index', 'show', 'loginHistories', 'socials', 'pointTrades', 'coinTrades', 'trash', 'export', 'exportPointTrades', 'exportCoinTrades']);
        $this->middleware('permission:users.edit')->only(['update', 'toggleStatus', 'resetPassword', 'resetContact', 'adjustBalance', 'extendVip', 'restore']);
        $this->middleware('permission:users.delete')->only(['destroy', 'forceDelete']);
    }

    /**
     * 用户列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

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

        if ($vip = $request->query('vip')) {
            if ($vip === '1') {
                $query->whereNotNull('vip_expires_at')->where('vip_expires_at', '>', now());
            } else {
                $query->where(function ($q) {
                    $q->whereNull('vip_expires_at')->orWhere('vip_expires_at', '<=', now());
                });
            }
        }

        if ($startDate = $request->query('login_start')) {
            $query->whereDate('last_login_at', '>=', $startDate);
        }

        if ($endDate = $request->query('login_end')) {
            $query->whereDate('last_login_at', '<=', $endDate);
        }

        if ($registerStart = $request->query('register_start')) {
            $query->whereDate('created_at', '>=', $registerStart);
        }

        if ($registerEnd = $request->query('register_end')) {
            $query->whereDate('created_at', '<=', $registerEnd);
        }

        // 按分群筛选（多值 AND：用户必须同时属于全部指定分群）
        $segmentIds = $request->query('segment_ids');
        if (is_string($segmentIds)) {
            $segmentIds = array_filter(array_map('trim', explode(',', $segmentIds)));
        }
        if (is_array($segmentIds) && $segmentIds !== []) {
            $segmentIds = array_values(array_unique(array_filter(
                array_map('intval', $segmentIds),
                fn ($v) => $v > 0
            )));
            foreach ($segmentIds as $sid) {
                $query->whereHas(
                    'memberships',
                    fn ($q) => $q->where('segment_id', $sid)
                );
            }
        }

        $items = $query->orderByDesc('id')->paginate(per_page($request));

        return UserResource::collection($items);
    }

    /**
     * 获取用户详情
     */
    public function show(User $user): UserDetailResource
    {
        return new UserDetailResource($user->load('profile'));
    }

    /**
     * 更新用户信息
     */
    public function update(UserUpdateRequest $request, User $user): UserResource
    {
        $validated = $request->validated();
        $user->update($validated);

        return new UserResource($user->fresh());
    }

    /**
     * 删除用户（软删除）
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(status: 204);
    }

    /**
     * 回收站用户列表（已软删除的用户）
     */
    public function trash(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 20);
        $keyword = $request->query('keyword');
        $trashedQuery = User::onlyTrashed()->withCount(['points', 'coins']);

        if ($keyword) {
            $trashedQuery->where(function ($query) use ($keyword): void {
                $query->where('username', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('sort_field')) {
            $sortField = $request->query('sort_field', 'deleted_at');
            $sortOrder = $request->query('sort_order', 'desc');
            $trashedQuery->orderBy($sortField, $sortOrder);
        } else {
            $trashedQuery->latest('deleted_at');
        }

        $items = $trashedQuery->paginate($perPage);

        return UserResource::collection($items);
    }

    /**
     * 恢复软删除的用户
     */
    public function restore(int $user): JsonResponse
    {
        $trashedUser = User::onlyTrashed()->findOrFail($user);
        $trashedUser->restore();

        return response()->json(status: 204);
    }

    /**
     * 彻底删除用户（不可恢复）
     */
    public function forceDelete(int $user): JsonResponse
    {
        $trashedUser = User::onlyTrashed()->findOrFail($user);
        $trashedUser->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * 启用/冻结用户
     */
    public function toggleStatus(User $user): UserResource
    {
        $user->status = $user->status->isActive() ? UserStatus::FROZEN : UserStatus::ACTIVE;
        $user->save();

        return new UserResource($user->fresh());
    }

    /**
     * 重置用户密码
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $user->resetPassword($data['password']);

        return response()->json(status: 204);
    }

    /**
     * 重置用户联系方式
     */
    public function resetContact(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:email,phone'],
            'value' => ['required', 'string'],
        ]);

        if ($data['type'] === 'email') {
            $request->validate([
                'value' => ['email', 'max:100', 'unique:users,email'],
            ]);
            $user->resetEmail($data['value']);
        } else {
            $request->validate([
                'value' => ['string', new \App\Rules\PhoneRule, 'unique:users,phone'],
            ]);
            $user->resetPhone($data['value']);
        }

        return response()->json(status: 204);
    }

    /**
     * 调整用户余额（积分/金币）
     */
    public function adjustBalance(UserAdjustBalanceRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $amount = (int) $data['amount'];
        $desc = $data['description'] ?? ($amount > 0 ? '后台充值' : '后台扣减');

        if ($data['type'] === 'points') {
            $type = $amount > 0 ? PointType::TYPE_ADMIN_RECHARGE : PointType::TYPE_ADMIN_DEDUCT;
            if ($amount > 0) {
                PointHelper::incr($user->id, $amount, $user, $type, $desc);
            } else {
                try {
                    PointHelper::decr($user->id, abs($amount), $user, $type, $desc);
                } catch (InsufficientPointsException $e) {
                    abort(422, $e->getMessage());
                }
            }
        } else {
            $type = $amount > 0 ? CoinType::TYPE_ADMIN_RECHARGE : CoinType::TYPE_ADMIN_DEDUCT;
            if ($amount > 0) {
                CoinHelper::incr($user, $amount, $user, $type, $desc);
            } else {
                try {
                    CoinHelper::decr($user, abs($amount), $user, $type, $desc);
                } catch (InsufficientCoinsException $e) {
                    abort(422, $e->getMessage());
                }
            }
        }

        return new UserResource($user->fresh());
    }

    /**
     * 延长用户VIP
     */
    public function extendVip(UserExtendVipRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $user->addVipDays((int) $data['days']);

        return new UserResource($user->fresh());
    }

    /**
     * 获取用户登录历史（分页）
     */
    public function loginHistories(Request $request, User $user): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = $user->loginHistories();

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

        if ($request->filled('result')) {
            $query->where('result', $request->boolean('result'));
        }

        $items = $query->orderByDesc('login_at')->paginate($perPage);

        return LoginHistoryResource::collection($items);
    }

    /**
     * 获取用户社交账号（分页）
     */
    public function socials(Request $request, User $user): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = $user->socials();

        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }

        return SocialResource::collection($query->paginate($perPage));
    }

    /**
     * 获取用户积分流水（分页）
     */
    public function pointTrades(Request $request, User $user): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = $user->points();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($keyword = $request->query('keyword')) {
            $query->where('description', 'like', "%{$keyword}%");
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return PointTradeResource::collection($query->paginate($perPage));
    }

    /**
     * 获取用户金币流水（分页）
     */
    public function coinTrades(Request $request, User $user): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = $user->coins();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($keyword = $request->query('keyword')) {
            $query->where('description', 'like', "%{$keyword}%");
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return CoinTradeResource::collection($query->paginate($perPage));
    }

    /**
     * 导出用户列表
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        return $this->exportDispatch(
            $request,
            $this->userExportClass(),
            [$this->userListFilters($request)],
            $this->exportFileName('users'),
            \App\Exports\Admin\UserListExport::SYNC_THRESHOLD,
        );
    }

    /**
     * 导出指定用户的积分流水
     */
    public function exportPointTrades(Request $request, User $user): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        return $this->exportDispatch(
            $request,
            $this->pointTradeExportClass(),
            [$user, $this->tradeListFilters($request)],
            $this->exportFileName("user_{$user->id}_points"),
            \App\Exports\Admin\PointTradeExport::SYNC_THRESHOLD,
        );
    }

    /**
     * 导出指定用户的金币流水
     */
    public function exportCoinTrades(Request $request, User $user): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        return $this->exportDispatch(
            $request,
            $this->coinTradeExportClass(),
            [$user, $this->tradeListFilters($request)],
            $this->exportFileName("user_{$user->id}_coins"),
            \App\Exports\Admin\CoinTradeExport::SYNC_THRESHOLD,
        );
    }
}
