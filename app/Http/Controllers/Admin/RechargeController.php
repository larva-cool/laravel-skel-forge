<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RechargeStatus;
use App\Exports\Admin\RechargeExport;
use App\Exports\HandlesExports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Wallet\RechargeConfirmRequest;
use App\Http\Requests\Admin\Wallet\RechargeIndexRequest;
use App\Http\Resources\Admin\RechargeResource;
use App\Models\Admin\Admin;
use App\Models\Wallet\Recharge;
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
class RechargeController extends Controller
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
    public function index(RechargeIndexRequest $request): AnonymousResourceCollection
    {
        $items = Recharge::query()
            ->filter($request->validated())
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return RechargeResource::collection($items);
    }

    /**
     * 充值订单详情
     */
    public function show(Recharge $recharge): RechargeResource
    {
        return new RechargeResource($recharge->load('owner'));
    }

    /**
     * 充值统计
     */
    public function stats(): JsonResponse
    {
        $successQuery = fn () => Recharge::query()->where('pay_status', RechargeStatus::SUCCESS->value);

        $totalAmount = (float) $successQuery()->sum('amount');
        $totalBonus = (float) $successQuery()->sum('bonus_amount');
        $todayAmount = (float) $successQuery()->whereDate('paid_at', Carbon::today())->sum('amount');

        $totalCount = Recharge::query()->count();
        $successCount = $successQuery()->count();

        return response()->json([
            'total_amount' => round($totalAmount, 2),
            'total_bonus' => round($totalBonus, 2),
            'today_amount' => round($todayAmount, 2),
            'total_count' => $totalCount,
            'success_count' => $successCount,
            'pending_count' => Recharge::query()->where('pay_status', RechargeStatus::PENDING->value)->count(),
            // 成功率保留两位小数，无订单时返回 0 而非除零
            'success_rate' => $totalCount > 0 ? round($successCount / $totalCount * 100, 2) : 0.0,
        ]);
    }

    /**
     * 手动确认到账
     */
    public function confirm(RechargeConfirmRequest $request, Recharge $recharge): RechargeResource
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $recharge = $this->rechargeService->confirmManually($recharge, $admin, $request->validated()['remark'] ?? null);

        return new RechargeResource($recharge->load('owner'));
    }

    /**
     * 关闭订单
     */
    public function close(RechargeConfirmRequest $request, Recharge $recharge): RechargeResource
    {
        $recharge = $this->rechargeService->close($recharge, $request->validated()['remark'] ?? null);

        return new RechargeResource($recharge->load('owner'));
    }

    /**
     * 导出充值订单
     */
    public function export(RechargeIndexRequest $request): BinaryFileResponse|JsonResponse
    {
        return $this->exportDispatch(
            $request,
            RechargeExport::class,
            [$request->validated()],
            $this->exportFileName('recharges'),
            RechargeExport::SYNC_THRESHOLD,
        );
    }
}
