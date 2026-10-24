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
use App\Models\Wallet\Recharge;
use App\Models\Wallet\WalletTrade;
use App\Models\Wallet\Withdraw;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 财务趋势分析工具。
 *
 * 按日聚合充值到账、提现出账与钱包净流入，并与上一等长周期环比，
 * 供管理员判断平台资金走势与异动。只读操作。
 */
class GetFinanceTrends implements RequiresPermission, Tool
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
        return '获取财务趋势分析：按日聚合近 N 天的充值到账金额/笔数、提现金额/笔数、钱包净流入（冻结/解冻不计入），并与上一等长周期对比给出环比变化百分比。当管理员询问资金走势、营收趋势、充值/提现异动、净流入变化等问题时使用。只读操作。';
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
     * 执行趋势分析。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $days = $request->integer('days', 7) ?: 7;
        $days = min(max($days, 1), 90);
        $since = Carbon::now()->subDays($days - 1)->startOfDay();
        $prevSince = $since->copy()->subDays($days);

        $current = $this->periodTotals($since, null);
        $previous = $this->periodTotals($prevSince, $since);

        return json_encode([
            'range' => [
                'days' => $days,
                'from' => $since->toDateString(),
                'to' => Carbon::now()->toDateString(),
                'previous_from' => $prevSince->toDateString(),
                'previous_to' => $since->copy()->subDay()->toDateString(),
            ],
            'totals' => [
                'current' => $current,
                'previous' => $previous,
                'change_pct' => [
                    'recharge_amount' => $this->changePct($current['recharge_amount'], $previous['recharge_amount']),
                    'withdraw_amount' => $this->changePct($current['withdraw_amount'], $previous['withdraw_amount']),
                    'net_flow' => $this->changePct($current['net_flow'], $previous['net_flow']),
                ],
            ],
            'daily' => $this->dailySeries($since, $days),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * 区间内汇总（充值按到账时间、提现按申请时间、净流入按流水时间）。
     *
     * @return array<string, mixed>
     */
    protected function periodTotals(Carbon $start, ?Carbon $end): array
    {
        $rechargeQuery = Recharge::query()
            ->where('pay_status', RechargeStatus::SUCCESS)
            ->where('paid_at', '>=', $start)
            ->when($end, fn ($q) => $q->where('paid_at', '<', $end));

        $withdrawQuery = Withdraw::query()
            ->where('status', WithdrawStatus::SUCCESS)
            ->where('created_at', '>=', $start)
            ->when($end, fn ($q) => $q->where('created_at', '<', $end));

        $netFlow = WalletTrade::query()
            ->where('created_at', '>=', $start)
            ->when($end, fn ($q) => $q->where('created_at', '<', $end))
            ->whereNotIn('type', $this->frozenOnlyTypes())
            ->sum('amount');

        return [
            'recharge_count' => (int) (clone $rechargeQuery)->count(),
            'recharge_amount' => (float) ((clone $rechargeQuery)->sum('amount') + (clone $rechargeQuery)->sum('bonus_amount')),
            'withdraw_count' => (int) (clone $withdrawQuery)->count(),
            'withdraw_amount' => (float) (clone $withdrawQuery)->sum('amount'),
            'net_flow' => (float) $netFlow,
        ];
    }

    /**
     * 按日序列（缺失日期补零）。
     *
     * @return array<int, array<string, mixed>>
     */
    protected function dailySeries(Carbon $since, int $days): array
    {
        $recharges = Recharge::query()
            ->where('pay_status', RechargeStatus::SUCCESS)
            ->where('paid_at', '>=', $since)
            ->selectRaw('DATE(paid_at) as d, COUNT(*) as c, SUM(amount + bonus_amount) as s')
            ->groupBy('d')
            ->get()
            ->keyBy('d')
            ->map(fn ($row) => ['count' => (int) $row->getAttribute('c'), 'amount' => (float) $row->getAttribute('s')])
            ->all();

        $withdraws = Withdraw::query()
            ->where('status', WithdrawStatus::SUCCESS)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c, SUM(amount) as s')
            ->groupBy('d')
            ->get()
            ->keyBy('d')
            ->map(fn ($row) => ['count' => (int) $row->getAttribute('c'), 'amount' => (float) $row->getAttribute('s')])
            ->all();

        $netFlows = WalletTrade::query()
            ->where('created_at', '>=', $since)
            ->whereNotIn('type', $this->frozenOnlyTypes())
            ->selectRaw('DATE(created_at) as d, SUM(amount) as s')
            ->groupBy('d')
            ->pluck('s', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $date,
                'recharge_count' => $recharges[$date]['count'] ?? 0,
                'recharge_amount' => $recharges[$date]['amount'] ?? 0.0,
                'withdraw_count' => $withdraws[$date]['count'] ?? 0,
                'withdraw_amount' => $withdraws[$date]['amount'] ?? 0.0,
                'net_flow' => $netFlows[$date] ?? 0.0,
            ];
        }

        return $series;
    }

    /**
     * 仅变动冻结余额的交易类型取值（统计收支时排除）。
     *
     * @return array<int, string>
     */
    protected function frozenOnlyTypes(): array
    {
        return array_values(array_map(
            static fn (WalletTradeType $type): string => $type->value,
            array_filter(WalletTradeType::cases(), static fn (WalletTradeType $type): bool => $type->isFrozenOnly()),
        ));
    }

    /**
     * 环比变化百分比；上期为 0 时返回 null 避免除零。
     */
    protected function changePct(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 2);
    }
}
