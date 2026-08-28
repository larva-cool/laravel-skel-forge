<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserSegment\Segment;
use App\Services\Segment\SegmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * 同步规则分群成员命令
 *
 * 遍历所有启用且类型为 RULE 的分群，调 SegmentService::syncRuleSegment
 * 增量刷新成员（diff SOURCE_RULE 写回）。可用于定时任务每日执行，
 * 也可手动指定分群 ID 或分群 code 单独同步。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Signature('segments:sync-rules {--segment=* : 指定分群 ID（可重复）；不传则同步全部启用规则分群} {--code=* : 指定分群 code（可重复）}')]
#[Description('同步规则分群成员：编译规则 → diff 现有 RULE 来源成员 → 增量 attach/detach')]
class SyncSegmentRulesCommand extends Command
{
    /**
     * 表格输出表头。
     *
     * @var array<int, string>
     */
    protected const TABLE_HEADERS = ['分群 ID', '分群名称', '新增', '移除', '总数', '耗时(ms)'];

    /**
     * Execute the console command.
     */
    public function handle(SegmentService $service): int
    {
        $segments = $this->resolveSegments();

        if ($segments->isEmpty()) {
            $this->warn('未找到需要同步的规则分群（type=RULE 且 status=1）。');

            return self::SUCCESS;
        }

        $this->info("开始同步 {$segments->count()} 个规则分群...");

        $rows = [];
        $failed = 0;
        $totalAdded = 0;
        $totalRemoved = 0;
        $totalDuration = 0;

        foreach ($segments as $segment) {
            $start = microtime(true);
            try {
                $result = $service->syncRuleSegment($segment);
                $durationMs = (int) ((microtime(true) - $start) * 1000);

                $rows[] = [
                    $segment->id,
                    $segment->name,
                    $result['added'],
                    $result['removed'],
                    $result['total'],
                    $durationMs,
                ];
                $totalAdded += $result['added'];
                $totalRemoved += $result['removed'];
                $totalDuration += $durationMs;
            } catch (Throwable $e) {
                $failed++;
                $this->error("分群 [{$segment->id}] {$segment->name} 同步失败：{$e->getMessage()}");
            }
        }

        $this->table(self::TABLE_HEADERS, $rows);

        $this->info(sprintf(
            '同步完成：共处理 %d 个分群，新增 %d，移除 %d，耗时 %d ms。%s',
            $segments->count(),
            $totalAdded,
            $totalRemoved,
            $totalDuration,
            $failed > 0 ? "（失败 {$failed} 个）" : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 解析要同步的分群集合
     *
     * 优先级：
     *  1. --segment=ID（可重复）
     *  2. --code=CODE（可重复）
     *  3. 全部 type=RULE 且 status=1
     */
    protected function resolveSegments()
    {
        $ids = (array) $this->option('segment');
        $codes = (array) $this->option('code');

        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        $codes = array_values(array_filter(array_map('trim', $codes), fn ($v) => $v !== ''));

        $query = Segment::query()->where('type', \App\Enums\SegmentType::RULE->value);

        if ($ids !== [] || $codes !== []) {
            $query->where(function ($q) use ($ids, $codes) {
                if ($ids !== []) {
                    $q->orWhereIn('id', $ids);
                }
                if ($codes !== []) {
                    $q->orWhereIn('code', $codes);
                }
            });
        } else {
            // 全量模式：仅同步启用的
            $query->where('status', true);
        }

        return $query->orderBy('id')->get();
    }
}
