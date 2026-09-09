<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Enums\WithdrawAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\CreateWithdrawRequest;
use App\Http\Resources\Api\V1\WithdrawResource;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use App\Services\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 前台提现控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct(
        protected WithdrawService $withdrawService,
    ) {
        $this->middleware('auth:sanctum');
        // 提交接口独立限流，防止刷单产生大量冻结流水
        $this->middleware('throttle:'.settings('wallet.withdraw_throttle', '5,1'))->only(['store']);
    }

    /**
     * 我的提现列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->withdraws();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderByDesc('id')->paginate(per_page($request));

        return WithdrawResource::collection($items);
    }

    /**
     * 提交提现申请
     */
    public function store(CreateWithdrawRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $withdraw = $this->withdrawService->submit(
            $user,
            (float) $data['amount'],
            WithdrawAccountType::from($data['account_type']),
            $data['account_info'],
            $request->ip(),
        );

        // 不使用 additional()，避免响应被强制包裹 data，破坏项目「无 data 包裹」约定
        return (new WithdrawResource($withdraw))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 提现详情
     */
    public function show(Withdraw $withdraw): WithdrawResource
    {
        $this->authorize('view', $withdraw);

        return new WithdrawResource($withdraw);
    }
}
