<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\OpenAccount\OpenAccountIndexRequest;
use App\Http\Requests\Admin\OpenAccount\OpenAccountStoreRequest;
use App\Http\Requests\Admin\OpenAccount\OpenAccountUpdateRequest;
use App\Http\Resources\Admin\OpenAccountResource;
use App\Models\Open\OpenAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 开放平台账户管理控制器（管理端）
 *
 * 管理外部合作方账户：开户、编辑、启停、轮换访问令牌。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class OpenAccountController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:open_accounts.index')->only(['index', 'show', 'trash']);
        $this->middleware('permission:open_accounts.create')->only(['store']);
        $this->middleware('permission:open_accounts.edit')->only(['update', 'toggleStatus', 'rotateToken', 'restore']);
        $this->middleware('permission:open_accounts.delete')->only(['destroy', 'forceDelete']);
    }

    /**
     * 开放平台账户列表（分页）
     */
    public function index(OpenAccountIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);
        $filters = $request->filters();

        $query = OpenAccount::query()->withCount('tokens');

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        if (! empty($filters['identity_status'])) {
            $query->where('identity_status', $filters['identity_status']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return OpenAccountResource::collection($items);
    }

    /**
     * 创建开放平台账户
     */
    public function store(OpenAccountStoreRequest $request): JsonResponse
    {
        $validated = $request->safe()->except(['password']);
        $account = OpenAccount::create($validated);

        if ($request->filled('password')) {
            $account->update(['password' => $request->input('password')]);
        }

        return response()->json(new OpenAccountResource($account), 201);
    }

    /**
     * 获取开放平台账户详情
     */
    public function show(OpenAccount $openAccount): OpenAccountResource
    {
        return new OpenAccountResource($openAccount->loadCount('tokens'));
    }

    /**
     * 更新开放平台账户
     */
    public function update(OpenAccountUpdateRequest $request, OpenAccount $openAccount): OpenAccountResource
    {
        $validated = $request->safe()->except(['password']);
        $openAccount->update($validated);

        if ($request->filled('password')) {
            $openAccount->update(['password' => $request->input('password')]);
        }

        return new OpenAccountResource($openAccount->fresh());
    }

    /**
     * 删除开放平台账户（软删，同时吊销所有令牌）
     */
    public function destroy(OpenAccount $openAccount): JsonResponse
    {
        $openAccount->tokens()->delete();
        $openAccount->delete();

        return response()->json(status: 204);
    }

    /**
     * 回收站列表（已软删除的开放平台账户）
     */
    public function trash(OpenAccountIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);
        $filters = $request->filters();

        $query = OpenAccount::onlyTrashed();

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('deleted_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('deleted_at', '<=', $filters['end_date']);
        }

        $items = $query->latest('deleted_at')->paginate($perPage);

        return OpenAccountResource::collection($items);
    }

    /**
     * 恢复软删除的开放平台账户
     */
    public function restore(int $openAccount): JsonResponse
    {
        $trashed = OpenAccount::onlyTrashed()->findOrFail($openAccount);
        $trashed->restore();

        return response()->json(status: 204);
    }

    /**
     * 彻底删除开放平台账户（不可恢复）
     */
    public function forceDelete(int $openAccount): JsonResponse
    {
        $trashed = OpenAccount::onlyTrashed()->findOrFail($openAccount);
        $trashed->tokens()->delete();
        $trashed->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * 启用/冻结开放平台账户
     */
    public function toggleStatus(OpenAccount $openAccount): OpenAccountResource
    {
        if ($openAccount->status->isActive()) {
            $openAccount->markFrozen();
            // 冻结时吊销所有令牌
            $openAccount->tokens()->delete();
        } else {
            $openAccount->markActive();
        }

        return new OpenAccountResource($openAccount->fresh());
    }

    /**
     * 轮换访问令牌：作废历史令牌并签发新令牌
     *
     * 明文 Token 仅在本次响应中返回一次，之后不可再查。
     */
    public function rotateToken(Request $request, OpenAccount $openAccount): JsonResponse
    {
        $token = $openAccount->rotateToken();

        return response()->json([
            'token' => $token->plainTextToken,
            'message' => '令牌已轮换，旧令牌已失效，请妥善保管新令牌。',
        ]);
    }
}
