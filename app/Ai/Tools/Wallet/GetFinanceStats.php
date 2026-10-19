<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\RechargeStatus;
use App\Enums\WalletTradeType;
use App\Enums\WithdrawStatus;
use App\Models\User;
use App\Models\Wallet\Recharge;
use App\Models\Wallet\WalletTrade;
use App\Models\Wallet\Withdraw;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 财务数据概览工具。
 *
 * 汇总充值、提现、钱包流水、平台资金存量等核心财务指标，
 * 供管理员快速了解平台资金状况。只读操作。
 */
class GetFinanceStats implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点（任一钱包相关权限即可）。
     */
    public function permission(): string|array|null
    {
        return ['wallet.index', 'wallet.orders', 'wallet.withdraws'];
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '获取平台财务数据概览，包括近 N 天充值到账金额/赠送金额/按渠道分布、提现申请各状态汇总（待审核/待打款/已打款/手续费）、钱包流水按类型的收支汇总、平台金币存量与用户冻结余额等核心资金指标。当管理员询问营收、充值、提现、资金状况等财务概览问题时使用。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->min(1)
                ->max(90)
                ->default(7)
                ->description('统计近 N 天的数据，默认 7 天，最大 90 天'),
        ];
    }

    /**
     * 执行统计。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $days = $request->integer('days', 7) ?: 7;
        $days = min(max($days, 1), 90);
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        return json_encode([
            'range' => [
                'days' => $days,
                'from' => $since->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ],
            'recharges' => $this->rechargeStats($since),
            'withdraws' => $this->withdrawStats($since),
            'wallet_trades' => $this->walletTradeStats($since),
            'stock' => $this->stockStats(),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * 充值相关统计（按到账时间口径）。
     *
     * @return array<string, mixed>
     */
    protected function rechargeStats(Carbon $since): array
    {
        $successQuery = Recharge::query()
            ->where('pay_status', RechargeStatus::SUCCESS)
            ->where('paid_at', '>=', $since);

        $byChannel = Recharge::query()
            ->where('pay_status', RechargeStatus::SUCCESS)
            ->where('paid_at', '>=', $since)
            ->selectRaw('pay_channel, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->groupBy('pay_channel')
            ->get()
            ->mapWithKeys(fn (Recharge $row) => [
                $row->pay_channel->value => [
                    'channel_label' => $row->pay_channel->label(),
                    'count' => (int) $row->getAttribute('total_count'),
                    'amount' => (float) $row->getAttribute('total_amount'),
                ],
            ])->all();

        return [
            'success_count' => (int) (clone $successQuery)->count(),
            'success_amount' => (float) (clone $successQuery)->sum('amount'),
            'bonus_amount' => (float) (clone $successQuery)->sum('bonus_amount'),
            'total_credited' => (float) ((clone $successQuery)->sum('amount') + (clone $successQuery)->sum('bonus_amount')),
            'pending_count' => Recharge::query()->where('pay_status', RechargeStatus::PENDING)->count(),
            'by_channel' => $byChannel,
        ];
    }

    /**
     * 提现相关统计（待审核/待打款为当前快照，其余按申请时间口径）。
     *
     * @return array<string, mixed>
     */
    protected function withdrawStats(Carbon $since): array
    {
        $successInRange = Withdraw::query()
            ->where('status', WithdrawStatus::SUCCESS)
            ->where('created_at', '>=', $since);

        return [
            'pending_count' => Withdraw::query()->where('status', WithdrawStatus::PENDING)->count(),
            'pending_amount' => (float) Withdraw::query()->where('status', WithdrawStatus::PENDING)->sum('amount'),
            'approved_count' => Withdraw::query()->where('status', WithdrawStatus::APPROVED)->count(),
            'approved_amount' => (float) Withdraw::query()->where('status', WithdrawStatus::APPROVED)->sum('actual_amount'),
            'success_count' => (int) (clone $successInRange)->count(),
            'success_amount' => (float) (clone $successInRange)->sum('amount'),
            'success_fee' => (float) (clone $successInRange)->sum('fee'),
            'success_actual_amount' => (float) (clone $successInRange)->sum('actual_amount'),
            'rejected_count' => Withdraw::query()
                ->where('status', WithdrawStatus::REJECTED)
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * 钱包流水统计（按交易类型分组，冻结/解冻不计入收支）。
     *
     * @return array<string, mixed>
     */
    protected function walletTradeStats(Carbon $since): array
    {
        $byType = WalletTrade::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('type, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn (WalletTrade $row) => [
                $row->type->value => [
                    'type_label' => $row->type->label(),
                    'count' => (int) $row->getAttribute('total_count'),
                    'amount' => (float) $row->getAttribute('total_amount'),
                ],
            ])->all();

        $income = 0.0;
        $expense = 0.0;

        foreach ($byType as $type => $row) {
            $enum = WalletTradeType::from($type);
            if ($enum->isFrozenOnly()) {
                continue;
            }
            if ($row['amount'] >= 0) {
                $income += $row['amount'];
            } else {
                $expense += $row['amount'];
            }
        }

        return [
            'by_type' => $byType,
            'income' => $income,
            'expense' => $expense,
            'net' => $income + $expense,
        ];
    }

    /**
     * 平台资金存量统计（当前快照）。
     *
     * @return array<string, mixed>
     */
    protected function stockStats(): array
    {
        return [
            'total_available_coins' => (int) User::query()->sum('available_coins'),
            'total_frozen_balance' => (float) User::query()->sum('frozen_balance'),
        ];
    }
}
