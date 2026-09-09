<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Enums\PayChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\CreateRechargeRequest;
use App\Http\Resources\Api\V1\RechargeResource;
use App\Models\User;
use App\Models\Wallet\Recharge;
use App\Services\RechargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 前台充值控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct(
        protected RechargeService $rechargeService,
    ) {
        $this->middleware('auth:sanctum');
        // 下单接口独立限流，防止刷单产生大量僵尸订单
        $this->middleware('throttle:'.settings('wallet.recharge_throttle', '10,1'))->only(['store']);
    }

    /**
     * 我的充值订单列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->recharges();

        if ($status = $request->query('pay_status')) {
            $query->where('pay_status', $status);
        }

        $items = $query->orderByDesc('id')->paginate(per_page($request));

        return RechargeResource::collection($items);
    }

    /**
     * 创建充值订单
     */
    public function store(CreateRechargeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $recharge = $this->rechargeService->create(
            $user,
            (float) $data['amount'],
            PayChannel::from($data['pay_channel']),
            $request->ip(),
        );

        // 不使用 additional()，避免响应被强制包裹 data，破坏项目「无 data 包裹」约定
        return (new RechargeResource($recharge))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 充值订单详情（供轮询支付状态）
     */
    public function show(Recharge $recharge): RechargeResource
    {
        $this->authorize('view', $recharge);

        return new RechargeResource($recharge);
    }
}
