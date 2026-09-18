<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\WalletTradeType;
use App\Exceptions\InsufficientBalanceException;
use App\Exports\Admin\WalletTradeExport;
use App\Exports\HandlesExports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Wallet\WalletAdjustRequest;
use App\Http\Requests\Admin\Wallet\WalletTradeIndexRequest;
use App\Http\Resources\Admin\WalletTradeResource;
use App\Http\Resources\Admin\WithdrawAccountResource;
use App\Models\User;
use App\Models\Wallet\WalletTrade;
use App\Support\WalletHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 后台钱包管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletController extends Controller
{
    use HandlesExports;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:wallet.index')->only(['index', 'show', 'stats', 'userWallet', 'userTrades', 'userWithdrawAccounts']);
        $this->middleware('permission:wallet.adjust')->only(['adjust']);
        $this->middleware('permission:wallet.export')->only(['export']);
    }

    /**
     * 全局钱包流水列表（分页）
     */
    public function index(WalletTradeIndexRequest $request): AnonymousResourceCollection
    {
        $items = WalletTrade::query()
            ->filter($request->validated())
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return WalletTradeResource::collection($items);
    }

    /**
     * 钱包流水详情
     */
    public function show(WalletTrade $trade): WalletTradeResource
    {
        return new WalletTradeResource($trade->load('owner'));
    }

    /**
     * 导出钱包流水
     */
    public function export(WalletTradeIndexRequest $request): BinaryFileResponse|JsonResponse
    {
        return $this->exportDispatch(
            $request,
            WalletTradeExport::class,
            [$request->validated()],
            $this->exportFileName('wallet_trades'),
            WalletTradeExport::SYNC_THRESHOLD,
        );
    }

    /**
     * 钱包统计
     */
    public function stats(): JsonResponse
    {
        $totals = User::query()
            ->selectRaw('coalesce(sum(balance), 0) as total_balance, coalesce(sum(frozen_balance), 0) as total_frozen')
            ->first();

        $income = (float) WalletTrade::query()
            ->whereIn('type', [
                WalletTradeType::RECHARGE->value,
                WalletTradeType::REFUND->value,
                WalletTradeType::ADMIN_RECHARGE->value,
            ])
            ->sum('amount');

        $expense = (float) WalletTrade::query()
            ->whereIn('type', [
                WalletTradeType::WITHDRAW->value,
                WalletTradeType::PAYMENT->value,
                WalletTradeType::ADMIN_DEDUCT->value,
            ])
            ->sum('amount');

        return response()->json([
            'total_balance' => round((float) ($totals->total_balance ?? 0), 2),
            'total_frozen' => round((float) ($totals->total_frozen ?? 0), 2),
            'total_income' => round($income, 2),
            'total_expense' => round(abs($expense), 2),
            'trade_count' => WalletTrade::query()->count(),
        ]);
    }

    /**
     * 指定用户钱包概览
     */
    public function userWallet(User $user): JsonResponse
    {
        $income = (float) $user->walletTrades()->where('amount', '>', 0)->sum('amount');
        $expense = (float) $user->walletTrades()->where('amount', '<', 0)->sum('amount');

        return response()->json([
            'user_id' => $user->id,
            'balance' => $user->balance,
            'frozen_balance' => $user->frozen_balance,
            'available_balance' => round((float) $user->balance - (float) $user->frozen_balance, 2),
            'total_balance' => round($user->totalBalance(), 2),
            'total_income' => round($income, 2),
            'total_expense' => round(abs($expense), 2),
        ]);
    }

    /**
     * 指定用户钱包流水（分页）
     */
    public function userTrades(WalletTradeIndexRequest $request, User $user): AnonymousResourceCollection
    {
        $filters = $request->validated();
        // 强制锁定持有者，避免越过路由参数查询他人流水
        unset($filters['owner_type'], $filters['owner_id']);

        $items = $user->walletTrades()
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return WalletTradeResource::collection($items);
    }

    /**
     * 指定用户收款账户列表（分页）
     */
    public function userWithdrawAccounts(Request $request, User $user): AnonymousResourceCollection
    {
        $items = $user->withdrawAccounts()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return WithdrawAccountResource::collection($items);
    }

    /**
     * 调整用户余额（正数充正，负数扣减）
     */
    public function adjust(WalletAdjustRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $amount = (float) $data['amount'];
        $desc = $data['description'];

        if ($amount > 0) {
            WalletHelper::incr($user, $amount, $user, WalletTradeType::ADMIN_RECHARGE, $desc);
        } else {
            try {
                WalletHelper::decr($user, abs($amount), $user, WalletTradeType::ADMIN_DEDUCT, $desc);
            } catch (InsufficientBalanceException $e) {
                abort(422, $e->getMessage());
            }
        }

        return $this->userWallet($user->fresh());
    }
}
