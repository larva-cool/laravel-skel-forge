<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WalletTradeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 前台钱包控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 我的钱包概览
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $income = (float) $user->walletTrades()->where('amount', '>', 0)->sum('amount');
        $expense = (float) $user->walletTrades()->where('amount', '<', 0)->sum('amount');

        return response()->json([
            'balance' => $user->balance,
            'frozen_balance' => $user->frozen_balance,
            'available_balance' => round((float) $user->balance - (float) $user->frozen_balance, 2),
            'total_balance' => round($user->totalBalance(), 2),
            'total_income' => round($income, 2),
            'total_expense' => round(abs($expense), 2),
        ]);
    }

    /**
     * 我的钱包流水（分页）
     */
    public function trades(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->walletTrades();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $items = $query->orderByDesc('id')->paginate(per_page($request));

        return WalletTradeResource::collection($items);
    }
}
