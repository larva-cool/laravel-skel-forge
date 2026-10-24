<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\RechargeStatus;
use App\Models\Wallet\Recharge;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 充值转化漏斗工具。
 *
 * 统计近 N 天充值订单的下单→到账转化与分渠道成功率，
 * 供管理员评估支付链路健康度。只读操作。
 */
class GetRechargeFunnel implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'wallet.orders';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '获取充值转化漏斗：统计近 N 天充值订单（按下单时间口径）的整体支付成功率、各状态分布、分渠道成功率与到账金额、平均支付耗时。当管理员询问充值转化率、支付成功率、渠道健康度、掉单情况时使用。只读操作。';
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

        $byStatus = Recharge::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('pay_status, COUNT(*) as c')
            ->groupBy('pay_status')
            ->pluck('c', 'pay_status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $created = array_sum($byStatus);
        $success = $byStatus[RechargeStatus::SUCCESS->value] ?? 0;

        return json_encode([
            'range' => [
                'days' => $days,
                'from' => $since->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ],
            'overall' => [
                'created_count' => $created,
                'success_count' => $success,
                'success_amount' => $this->successAmount($since),
                'pay_rate' => $created > 0 ? round($success / $created * 100, 2) : null,
                'by_status' => $this->statusBreakdown($byStatus),
                'avg_pay_minutes' => $this->avgPayMinutes($since),
            ],
            'by_channel' => $this->channelFunnel($since),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * 已到账订单的入账总额（充值 + 赠送）。
     */
    protected function successAmount(Carbon $since): float
    {
        return (float) Recharge::query()
            ->where('created_at', '>=', $since)
            ->where('pay_status', RechargeStatus::SUCCESS)
            ->selectRaw('SUM(amount + bonus_amount) as s')
            ->value('s');
    }

    /**
     * 状态分布（含可读标签）。
     *
     * @param  array<string, int>  $byStatus
     * @return array<string, array<string, mixed>>
     */
    protected function statusBreakdown(array $byStatus): array
    {
        $breakdown = [];
        foreach (RechargeStatus::cases() as $status) {
            $breakdown[$status->value] = [
                'status_label' => $status->label(),
                'count' => $byStatus[$status->value] ?? 0,
            ];
        }

        return $breakdown;
    }

    /**
     * 分渠道漏斗。
     *
     * @return array<string, array<string, mixed>>
     */
    protected function channelFunnel(Carbon $since): array
    {
        return Recharge::query()
            ->where('created_at', '>=', $since)
            ->select('pay_channel')
            ->selectRaw('COUNT(*) as created_count')
            ->selectRaw('SUM(CASE WHEN pay_status = ? THEN 1 ELSE 0 END) as success_count', [RechargeStatus::SUCCESS->value])
            ->selectRaw('SUM(CASE WHEN pay_status = ? THEN amount + bonus_amount ELSE 0 END) as success_amount', [RechargeStatus::SUCCESS->value])
            ->groupBy('pay_channel')
            ->get()
            ->mapWithKeys(function (Recharge $row): array {
                $created = (int) $row->getAttribute('created_count');
                $success = (int) $row->getAttribute('success_count');

                return [
                    $row->pay_channel->value => [
                        'channel_label' => $row->pay_channel->label(),
                        'created_count' => $created,
                        'success_count' => $success,
                        'success_amount' => (float) $row->getAttribute('success_amount'),
                        'pay_rate' => $created > 0 ? round($success / $created * 100, 2) : null,
                    ],
                ];
            })->all();
    }

    /**
     * 平均支付耗时（分钟，下单→到账）；无到账订单时返回 null。
     */
    protected function avgPayMinutes(Carbon $since): ?float
    {
        $totalSeconds = 0;
        $count = 0;

        Recharge::query()
            ->where('created_at', '>=', $since)
            ->where('pay_status', RechargeStatus::SUCCESS)
            ->whereNotNull('paid_at')
            ->select(['id', 'created_at', 'paid_at'])
            ->chunkById(1000, function ($orders) use (&$totalSeconds, &$count): void {
                foreach ($orders as $order) {
                    $totalSeconds += max($order->paid_at->getTimestamp() - $order->created_at->getTimestamp(), 0);
                    $count++;
                }
            }, 'id');

        return $count > 0 ? round($totalSeconds / $count / 60, 1) : null;
    }
}
