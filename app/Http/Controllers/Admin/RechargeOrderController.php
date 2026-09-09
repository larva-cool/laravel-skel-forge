<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RechargeStatus;
use App\Exports\Admin\RechargeOrderExport;
use App\Exports\HandlesExports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Wallet\RechargeOrderConfirmRequest;
use App\Http\Requests\Admin\Wallet\RechargeOrderIndexRequest;
use App\Http\Resources\Admin\RechargeOrderResource;
use App\Models\Admin\Admin;
use App\Models\Wallet\RechargeOrder;
use App\Services\RechargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 后台充值订单管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeOrderController extends Controller
{
    use HandlesExports;

    /**
     * Constructor.
     */
    public function __construct(
        protected RechargeService $rechargeService,
    ) {
        $this->middleware('auth:admin');
        $this->middleware('permission:wallet.orders')->only(['index', 'show', 'stats', 'confirm', 'close']);
        $this->middleware('permission:wallet.export')->only(['export']);
    }

    /**
     * 充值订单列表（分页）
     */
    public function index(RechargeOrderIndexRequest $request): AnonymousResourceCollection
    {
        $items = RechargeOrder::query()
            ->filter($request->validated())
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return RechargeOrderResource::collection($items);
    }

    /**
     * 充值订单详情
     */
    public function show(RechargeOrder $order): RechargeOrderResource
    {
        return new RechargeOrderResource($order->load('owner'));
    }

    /**
     * 充值统计
     */
    public function stats(): JsonResponse
    {
        $successQuery = fn () => RechargeOrder::query()->where('pay_status', RechargeStatus::SUCCESS->value);

        $totalAmount = (float) $successQuery()->sum('amount');
        $totalBonus = (float) $successQuery()->sum('bonus_amount');
        $todayAmount = (float) $successQuery()->whereDate('paid_at', Carbon::today())->sum('amount');

        $totalCount = RechargeOrder::query()->count();
        $successCount = $successQuery()->count();

        return response()->json([
            'total_amount' => round($totalAmount, 2),
            'total_bonus' => round($totalBonus, 2),
            'today_amount' => round($todayAmount, 2),
            'total_count' => $totalCount,
            'success_count' => $successCount,
            'pending_count' => RechargeOrder::query()->where('pay_status', RechargeStatus::PENDING->value)->count(),
            // 成功率保留两位小数，无订单时返回 0 而非除零
            'success_rate' => $totalCount > 0 ? round($successCount / $totalCount * 100, 2) : 0.0,
        ]);
    }

    /**
     * 手动确认到账
     */
    public function confirm(RechargeOrderConfirmRequest $request, RechargeOrder $order): RechargeOrderResource
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $order = $this->rechargeService->confirmManually($order, $admin, $request->validated()['remark'] ?? null);

        return new RechargeOrderResource($order->load('owner'));
    }

    /**
     * 关闭订单
     */
    public function close(RechargeOrderConfirmRequest $request, RechargeOrder $order): RechargeOrderResource
    {
        $order = $this->rechargeService->close($order, $request->validated()['remark'] ?? null);

        return new RechargeOrderResource($order->load('owner'));
    }

    /**
     * 导出充值订单
     */
    public function export(RechargeOrderIndexRequest $request): BinaryFileResponse|JsonResponse
    {
        return $this->exportDispatch(
            $request,
            RechargeOrderExport::class,
            [$request->validated()],
            $this->exportFileName('recharge_orders'),
            RechargeOrderExport::SYNC_THRESHOLD,
        );
    }
}
