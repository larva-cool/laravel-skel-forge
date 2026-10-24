<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Stats;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\User\UserStat;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户增长趋势分析工具。
 *
 * 汇总新增/活跃/留存趋势与环比变化，供管理员判断用户增长质量。
 * 只读操作。
 */
class GetUserGrowthStats implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'users.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '获取用户增长趋势分析：近 N 天新增/活跃用户按日走势、与上一等长周期的新增环比、次日与 7 日留存率（按注册队列的最后活跃时间推算）、VIP 转化规模。当管理员询问用户增长、活跃度、留存率、新增趋势等运营问题时使用。只读操作。';
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
                ->default(30)
                ->description('统计近 N 天的数据，默认 30 天，最大 90 天'),
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

        $days = $request->integer('days', 30) ?: 30;
        $days = min(max($days, 1), 90);
        $since = Carbon::now()->subDays($days - 1)->startOfDay();
        $prevSince = $since->copy()->subDays($days);

        $newInRange = User::query()->where('created_at', '>=', $since)->count();
        $newPrevRange = User::query()->where('created_at', '>=', $prevSince)->where('created_at', '<', $since)->count();

        return json_encode([
            'range' => [
                'days' => $days,
                'from' => $since->toDateString(),
                'to' => Carbon::now()->toDateString(),
                'previous_from' => $prevSince->toDateString(),
                'previous_to' => $since->copy()->subDay()->toDateString(),
            ],
            'summary' => [
                'total_users' => User::query()->count(),
                'active_users' => User::query()->where('status', UserStatus::ACTIVE)->count(),
                'new_in_range' => $newInRange,
                'new_prev_range' => $newPrevRange,
                'new_change_pct' => $this->changePct($newInRange, $newPrevRange),
                'active_in_range' => User::query()->where('last_active_at', '>=', $since)->count(),
                'vip_active' => User::query()->whereNotNull('vip_expires_at')->where('vip_expires_at', '>', Carbon::now())->count(),
            ],
            'retention' => [
                'day1' => $this->retention($since, 1),
                'day7' => $this->retention($since, 7),
            ],
            'daily' => $this->dailyTrend($since),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * N 日留存率（注册满 N 天的队列中，最后活跃时间距注册时间 ≥ N 天的占比）。
     *
     * @return array<string, mixed>
     */
    protected function retention(Carbon $since, int $retentionDays): array
    {
        $eligibleEnd = Carbon::now()->subDays($retentionDays);

        if ($eligibleEnd->lessThanOrEqualTo($since)) {
            return ['cohort' => 0, 'retained' => 0, 'rate' => null];
        }

        $cohort = 0;
        $retained = 0;

        User::query()
            ->whereBetween('created_at', [$since, $eligibleEnd])
            ->select(['id', 'created_at', 'last_active_at'])
            ->chunkById(1000, function ($users) use (&$cohort, &$retained, $retentionDays): void {
                foreach ($users as $user) {
                    $cohort++;
                    if ($user->last_active_at !== null
                        && $user->last_active_at->greaterThanOrEqualTo($user->created_at->copy()->addDays($retentionDays))) {
                        $retained++;
                    }
                }
            }, 'id');

        return [
            'cohort' => $cohort,
            'retained' => $retained,
            'rate' => $cohort > 0 ? round($retained / $cohort * 100, 2) : null,
        ];
    }

    /**
     * 按日新增/活跃走势（来自每日统计快照表）。
     *
     * @return array<int, array<string, mixed>>
     */
    protected function dailyTrend(Carbon $since): array
    {
        return UserStat::query()
            ->where('stat_date', '>=', $since->toDateString())
            ->orderBy('stat_date')
            ->get()
            ->map(fn (UserStat $stat) => [
                'date' => $stat->stat_date?->toDateString(),
                'new' => $stat->new_user_count,
                'active' => $stat->active_user_count,
                'total' => $stat->total_user_count,
            ])->all();
    }

    /**
     * 环比变化百分比；上期为 0 时返回 null 避免除零。
     */
    protected function changePct(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 2);
    }
}
