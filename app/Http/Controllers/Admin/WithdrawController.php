<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawStatus;
use App\Exports\Admin\WithdrawExport;
use App\Exports\HandlesExports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Wallet\WithdrawFailRequest;
use App\Http\Requests\Admin\Wallet\WithdrawIndexRequest;
use App\Http\Requests\Admin\Wallet\WithdrawRejectRequest;
use App\Http\Requests\Admin\Wallet\WithdrawRemarkRequest;
use App\Http\Resources\Admin\WithdrawResource;
use App\Models\Admin\Admin;
use App\Models\Wallet\Withdraw;
use App\Services\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 后台提现申请管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawController extends Controller
{
    use HandlesExports;

    /**
     * Constructor.
     */
    public function __construct(
        protected WithdrawService $withdrawService,
    ) {
        $this->middleware('auth:admin');
        $this->middleware('permission:wallet.withdraws')->only(['index', 'show', 'stats', 'approve', 'reject', 'transfer', 'fail']);
        $this->middleware('permission:wallet.export')->only(['export']);
    }

    /**
     * 提现申请列表（分页）
     */
    public function index(WithdrawIndexRequest $request): AnonymousResourceCollection
    {
        $items = Withdraw::query()
            ->filter($request->validated())
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return WithdrawResource::collection($items);
    }

    /**
     * 提现申请详情
     */
    public function show(Withdraw $withdraw): WithdrawResource
    {
        return new WithdrawResource($withdraw->load('owner'));
    }

    /**
     * 提现统计
     */
    public function stats(): JsonResponse
    {
        $successQuery = fn () => Withdraw::query()->where('status', WithdrawStatus::SUCCESS->value);
        $pendingQuery = fn () => Withdraw::query()->where('status', WithdrawStatus::PENDING->value);

        $totalAmount = (float) $successQuery()->sum('amount');
        $totalFee = (float) $successQuery()->sum('fee');
        $todayAmount = (float) $successQuery()->whereDate('transfer_at', Carbon::today())->sum('amount');

        $totalCount = Withdraw::query()->count();
        $successCount = $successQuery()->count();
        $pendingCount = $pendingQuery()->count();

        return response()->json([
            'total_amount' => round($totalAmount, 2),
            'total_fee' => round($totalFee, 2),
            'today_amount' => round($todayAmount, 2),
            'total_count' => $totalCount,
            'success_count' => $successCount,
            'pending_count' => $pendingCount,
            'success_rate' => $totalCount > 0 ? round($successCount / $totalCount * 100, 2) : 0.0,
        ]);
    }

    /**
     * 审核通过（保持冻结，等待打款）
     */
    public function approve(WithdrawRemarkRequest $request, Withdraw $withdraw): WithdrawResource
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $withdraw = $this->withdrawService->approve($withdraw, $admin, $request->validated()['remark'] ?? null);

        return new WithdrawResource($withdraw->load('owner'));
    }

    /**
     * 审核驳回（退回冻结金额）
     */
    public function reject(WithdrawRejectRequest $request, Withdraw $withdraw): WithdrawResource
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validated();

        $withdraw = $this->withdrawService->reject($withdraw, $admin, $data['reason'], $data['remark'] ?? null);

        return new WithdrawResource($withdraw->load('owner'));
    }

    /**
     * 标记已打款（结算冻结金额）
     */
    public function transfer(WithdrawRemarkRequest $request, Withdraw $withdraw): WithdrawResource
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $withdraw = $this->withdrawService->markTransferred($withdraw, $admin, $request->validated()['remark'] ?? null);

        return new WithdrawResource($withdraw->load('owner'));
    }

    /**
     * 标记打款失败（退回冻结金额）
     */
    public function fail(WithdrawFailRequest $request, Withdraw $withdraw): WithdrawResource
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validated();

        $withdraw = $this->withdrawService->markFailed($withdraw, $admin, $data['reason'], $data['remark'] ?? null);

        return new WithdrawResource($withdraw->load('owner'));
    }

    /**
     * 导出提现申请
     */
    public function export(WithdrawIndexRequest $request): BinaryFileResponse|JsonResponse
    {
        return $this->exportDispatch(
            $request,
            WithdrawExport::class,
            [$request->validated()],
            $this->exportFileName('withdraws'),
            WithdrawExport::SYNC_THRESHOLD,
        );
    }
}
