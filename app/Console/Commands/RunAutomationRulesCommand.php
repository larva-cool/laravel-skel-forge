<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Automation\AutomationEngineService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 执行自动化运营规则命令
 *
 * 扫描所有启用的自动化规则，对到达推送时间的规则执行人群匹配与触达。
 * 由 schedules 表动态调度（默认每小时第 10 分钟执行一次），
 * 也可手动指定规则 ID 或运行日期单独执行（如补跑昨天遗漏的规则）。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Signature('automation:run {--rule= : 指定规则 ID，不传则扫描全部启用规则} {--date= : 指定运行日期（Y-m-d），默认今天} {--force : 忽略 send_time 时间窗立即执行}')]
#[Description('执行自动化运营规则：匹配目标用户 → 发送提醒通知 → 发放奖励 → 记录触发日志（幂等）')]
class RunAutomationRulesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AutomationEngineService $engine): int
    {
        $ruleId = $this->option('rule');
        $ruleId = $ruleId !== null && (int) $ruleId > 0 ? (int) $ruleId : null;

        $dateOption = $this->option('date');
        $runDate = null;
        if (is_string($dateOption) && $dateOption !== '') {
            try {
                $runDate = Carbon::createFromFormat('Y-m-d', $dateOption);
            } catch (\Throwable) {
                $runDate = null;
            }

            if (! $runDate instanceof Carbon || $runDate->format('Y-m-d') !== $dateOption) {
                $this->error("日期格式错误：{$dateOption}，应为 Y-m-d");

                return self::FAILURE;
            }
        }

        $summaries = $engine->run($runDate, $ruleId, (bool) $this->option('force'));

        if ($summaries === []) {
            $this->warn('没有需要执行的规则（全部未启用、未到推送时间或指定规则不存在）。');

            return self::SUCCESS;
        }

        $rows = array_map(fn (array $item) => [
            $item['rule_id'],
            $item['rule_name'],
            $item['trigger_type'],
            $item['event_date'],
            $item['sent'],
            $item['failed'],
            $item['skipped'],
            $item['note'] ?? '-',
        ], $summaries);

        $this->table(['规则 ID', '规则名称', '类型', '事件日期', '触达', '失败', '跳过', '备注'], $rows);

        $totalFailed = array_sum(array_column($summaries, 'failed'));

        $this->info(sprintf(
            '执行完成：共处理 %d 条规则，触达 %d 人，失败 %d 人，跳过 %d 人。',
            count($summaries),
            array_sum(array_column($summaries, 'sent')),
            $totalFailed,
            array_sum(array_column($summaries, 'skipped')),
        ));

        return self::SUCCESS;
    }
}
