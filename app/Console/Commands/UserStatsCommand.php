<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User\UserStat;
use App\Services\UserStatsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 用户统计命令
 *
 * 统计指定日期（默认昨天）的用户总数、新增数、活跃数，以及积分、金币的存量与增减，
 * 并写入 user_stats 表。支持通过 --start/--end 选项批量补录历史日期。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Signature('stats:user {date? : 统计日期，格式 YYYY-MM-DD，默认昨天} {--start= : 批量补录起始日期 YYYY-MM-DD} {--end= : 批量补录结束日期 YYYY-MM-DD，默认今天}')]
#[Description('统计用户数据（总数、新增、活跃、积分、金币）并写入 user_stats 表')]
class UserStatsCommand extends Command
{
    /**
     * 输出表格的表头。
     *
     * @var array<int, string>
     */
    protected const TABLE_HEADERS = [
        '日期', '用户总数', '新增用户', '活跃用户', '积分存量', '积分增/减', '金币存量', '金币增/减',
    ];

    /**
     * Execute the console command.
     */
    public function handle(UserStatsService $service): int
    {
        $start = $this->option('start');
        $end = $this->option('end');

        // 批量补录模式
        if (is_string($start)) {
            return $this->backfill($service, $start, is_string($end) ? $end : null);
        }

        // 单日统计模式
        $dateArgument = $this->argument('date');
        $date = is_string($dateArgument)
            ? Carbon::parse($dateArgument)->startOfDay()
            : Carbon::yesterday();

        $this->info("正在统计 {$date->toDateString()} 的用户数据...");

        $stat = $service->record($date);

        $this->info('统计完成：');
        $this->table(self::TABLE_HEADERS, [$this->toRow($stat)]);

        return self::SUCCESS;
    }

    /**
     * 批量补录历史日期。
     */
    protected function backfill(UserStatsService $service, string $start, ?string $end): int
    {
        try {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = $end ? Carbon::parse($end)->startOfDay() : Carbon::today();
        } catch (\Throwable $e) {
            $this->error("日期格式不正确：{$e->getMessage()}");

            return self::FAILURE;
        }

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $days = $startDate->diffInDays($endDate) + 1;
        $this->info("开始补录 {$startDate->toDateString()} 至 {$endDate->toDateString()}（共 {$days} 天）的用户数据...");

        $records = $service->backfill($startDate, $endDate);

        $rows = array_map(fn ($stat) => $this->toRow($stat), $records);

        $this->table(self::TABLE_HEADERS, $rows);
        $this->info('补录完成，共写入 '.count($records).' 条记录。');

        return self::SUCCESS;
    }

    /**
     * 将统计记录转换为表格行。
     *
     * @return array<int, mixed>
     */
    protected function toRow(UserStat $stat): array
    {
        return [
            $stat->stat_date?->toDateString(),
            $stat->total_user_count,
            $stat->new_user_count,
            $stat->active_user_count,
            $stat->total_point_count,
            '+'.$stat->incr_point_count.' / -'.$stat->decr_point_count,
            $stat->total_coin_count,
            '+'.$stat->incr_coin_count.' / -'.$stat->decr_coin_count,
        ];
    }
}
