<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\StoreWithdrawAccountRequest;
use App\Http\Requests\Api\V1\Wallet\UpdateWithdrawAccountRequest;
use App\Http\Resources\Api\V1\WithdrawAccountResource;
use App\Models\User;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * 前台提现收款账户控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawAccountController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 我的收款账户列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->withdrawAccounts()->orderByDesc('is_default')->orderByDesc('id');

        if ($accountType = $request->query('account_type')) {
            $query->where('account_type', $accountType);
        }

        $items = $query->paginate(per_page($request));

        return WithdrawAccountResource::collection($items);
    }

    /**
     * 添加收款账户
     */
    public function store(StoreWithdrawAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        // 同类型同账号不可重复绑定
        $exists = $user->withdrawAccounts()
            ->where('account_type', $data['account_type'])
            ->where('account_no', $data['account_no'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['account_no' => '该收款账号已绑定']);
        }

        $account = $user->withdrawAccounts()->create([
            'account_type' => $data['account_type'],
            'real_name' => $data['real_name'],
            'account_no' => $data['account_no'],
            'bank_name' => $data['bank_name'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'is_verified' => true,
        ]);

        // 首个账户自动设为默认
        if ($user->withdrawAccounts()->count() === 1) {
            $account->setDefault();
        } elseif (! empty($data['is_default'])) {
            $account->setDefault();
        }

        return (new WithdrawAccountResource($account->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 修改收款账户
     */
    public function update(UpdateWithdrawAccountRequest $request, WithdrawAccount $withdrawAccount): WithdrawAccountResource
    {
        $this->authorize('update', $withdrawAccount);

        $data = $request->validated();

        $updateData = array_filter([
            'real_name' => $data['real_name'] ?? null,
            'account_no' => $data['account_no'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($value) => $value !== null);

        $withdrawAccount->update($updateData);

        if (! empty($data['is_default'])) {
            $withdrawAccount->setDefault();
        }

        return new WithdrawAccountResource($withdrawAccount->fresh());
    }

    /**
     * 删除收款账户
     */
    public function destroy(WithdrawAccount $withdrawAccount): JsonResponse
    {
        $this->authorize('delete', $withdrawAccount);

        $withdrawAccount->delete();

        return response()->json(null, 204);
    }

    /**
     * 设为默认账户
     */
    public function setDefault(WithdrawAccount $withdrawAccount): WithdrawAccountResource
    {
        $this->authorize('update', $withdrawAccount);

        $withdrawAccount->setDefault();

        return new WithdrawAccountResource($withdrawAccount->fresh());
    }
}
