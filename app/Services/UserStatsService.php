<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Models\Coin\CoinTrade;
use App\Models\Point\PointTrade;
use App\Models\User;
use App\Models\User\UserStat;
use Illuminate\Support\Carbon;

/**
 * 用户统计服务
 *
 * 按日汇总前台用户的总数、新增数、活跃数，以及积分、金币的存量与当日增减，
 * 并写入 user_stats 表。支持统计任意指定日期，可用于定时任务每日执行或历史数据补录。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserStatsService
{
    /**
     * upsert 时需要覆盖更新的字段。
     *
     * @var array<int, string>
     */
    protected const UPDATE_COLUMNS = [
        'total_user_count',
        'new_user_count',
        'active_user_count',
        'total_point_count',
        'incr_point_count',
        'decr_point_count',
        'total_coin_count',
        'incr_coin_count',
        'decr_coin_count',
    ];

    /**
     * 统计指定日期的用户数据并入库。
     *
     * 若该日期的统计记录已存在，则覆盖更新（基于 stat_date 唯一键 upsert）。
     *
     * @param  \Illuminate\Support\Carbon|string|null  $date  统计日期，默认为今天
     * @return UserStat 统计记录
     */
    public function record(Carbon|string|null $date = null): UserStat
    {
        $statDate = $this->parseDate($date);

        $stats = $this->calculate($statDate);

        UserStat::query()->upsert([$stats], ['stat_date'], self::UPDATE_COLUMNS);

        return UserStat::query()->where('stat_date', $statDate->toDateString())->firstOrFail();
    }

    /**
     * 补录一个日期区间内每天的用户统计。
     *
     * @param  \Illuminate\Support\Carbon|string  $start  起始日期
     * @param  \Illuminate\Support\Carbon|string|null  $end  结束日期，默认为今天
     * @return array<int, UserStat> 写入的记录集合
     */
    public function backfill(Carbon|string $start, Carbon|string|null $end = null): array
    {
        $startDate = $this->parseDate($start)->startOfDay();
        $endDate = $this->parseDate($end)->startOfDay();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $records = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $stats = $this->calculate($date);
            UserStat::query()->upsert([$stats], ['stat_date'], self::UPDATE_COLUMNS);
            $records[] = UserStat::query()->where('stat_date', $date->toDateString())->firstOrFail();
        }

        return $records;
    }

    /**
     * 计算指定日期的用户统计指标。
     *
     * @return array{stat_date: string, total_user_count: int, new_user_count: int, active_user_count: int, total_point_count: int, incr_point_count: int, decr_point_count: int, total_coin_count: int, incr_coin_count: int, decr_coin_count: int}
     */
    public function calculate(Carbon $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        return [
            'stat_date' => $dayStart->toDateString(),
            // 截止当日结束的累计用户总数（含已软删除用户）
            'total_user_count' => User::query()
                ->where('created_at', '<=', $dayEnd)
                ->count(),
            // 当日新注册用户数
            'new_user_count' => User::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count(),
            // 当日活跃用户数（最后活动时间落在当天）
            'active_user_count' => User::query()
                ->whereBetween('last_active_at', [$dayStart, $dayEnd])
                ->count(),
            // 截止当日结束的积分存量（发放减消耗，不为负）
            'total_point_count' => max(0, (int) PointTrade::query()
                ->where('created_at', '<=', $dayEnd)
                ->sum('points')),
            // 当日发放积分总量
            'incr_point_count' => (int) PointTrade::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('points', '>', 0)
                ->sum('points'),
            // 当日消耗积分总量（取绝对值）
            'decr_point_count' => abs((int) PointTrade::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('points', '<', 0)
                ->sum('points')),
            // 截止当日结束的金币存量（发放减消耗，不为负）
            'total_coin_count' => max(0, (int) CoinTrade::query()
                ->where('created_at', '<=', $dayEnd)
                ->sum('coins')),
            // 当日发放金币总量
            'incr_coin_count' => (int) CoinTrade::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('coins', '>', 0)
                ->sum('coins'),
            // 当日消耗金币总量（取绝对值）
            'decr_coin_count' => abs((int) CoinTrade::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('coins', '<', 0)
                ->sum('coins')),
        ];
    }

    /**
     * 将日期参数统一解析为 Carbon 实例。
     */
    protected function parseDate(Carbon|string|null $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->startOfDay();
        }

        if (is_string($date)) {
            return Carbon::parse($date)->startOfDay();
        }

        return Carbon::today();
    }
}
