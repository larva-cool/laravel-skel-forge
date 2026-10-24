<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户钱包概览
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.overview.get')]
#[Description('获取当前登录用户的钱包概览，包括余额、冻结金额、可用金额、累计收支等。')]
#[IsReadOnly]
#[IsIdempotent]
class GetWalletOverviewTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();

        $income = (float) $user->walletTrades()->where('amount', '>', 0)->sum('amount');
        $expense = (float) $user->walletTrades()->where('amount', '<', 0)->sum('amount');

        return Response::structured([
            'balance' => $user->balance,
            'frozen_balance' => $user->frozen_balance,
            'available_balance' => round((float) $user->balance - (float) $user->frozen_balance, 2),
            'total_balance' => round($user->totalBalance(), 2),
            'total_income' => round($income, 2),
            'total_expense' => round(abs($expense), 2),
        ]);
    }
}
