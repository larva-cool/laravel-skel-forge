<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Segment;

use App\Models\User;
use App\Models\UserSegment\Membership;
use App\Models\UserSegment\Segment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 用户分群业务服务
 *
 * 负责分群与用户之间的打标/解标编排，以及规则分群的同步刷新。
 * 与 SegmentRuleEvaluator 协作：Evaluator 负责"规则 → 闭包"，
 * 本服务负责"闭包应用到 user_segment_memberships 增量同步"。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentService
{
    /** 单次批量打标/解标上限（超出返回 InvalidArgumentException） */
    public const BATCH_LIMIT = 1000;

    public function __construct(private readonly SegmentRuleEvaluator $evaluator) {}

    /**
     * 单分群批量绑定用户
     *
     * @param  array<int, int>  $userIds
     * @return int 新增绑定数（不含已存在）
     */
    public function attachUsers(Segment $segment, array $userIds, int $source = Membership::SOURCE_MANUAL): int
    {
        $this->assertBatchSize($userIds);
        $userIds = $this->filterExistingUserIds($userIds);

        if ($userIds === []) {
            return 0;
        }

        $now = Carbon::now();
        $rows = array_map(fn (int $uid) => [
            'segment_id' => $segment->id,
            'user_id' => $uid,
            'source' => $source,
            'created_at' => $now,
        ], $userIds);

        // insertOrIgnore 走唯一键 (segment_id, user_id) 保证幂等
        return DB::table('user_segment_memberships')->insertOrIgnore($rows);
    }

    /**
     * 单分群批量解绑用户
     *
     * @param  array<int, int>  $userIds
     * @return int 实际删除行数
     */
    public function detachUsers(Segment $segment, array $userIds): int
    {
        $this->assertBatchSize($userIds);

        if ($userIds === []) {
            return 0;
        }

        return Membership::query()
            ->where('segment_id', $segment->id)
            ->whereIn('user_id', $userIds)
            ->delete();
    }

    /**
     * 笛卡尔批量打标：分群列表 × 用户列表
     *
     * @param  array<int, int>  $segmentIds
     * @param  array<int, int>  $userIds
     * @return int 新增绑定数
     */
    public function batchAttach(array $segmentIds, array $userIds, int $source = Membership::SOURCE_MANUAL): int
    {
        $this->assertBatchSize($segmentIds);
        $this->assertBatchSize($userIds);

        $userIds = $this->filterExistingUserIds($userIds);
        if ($userIds === [] || $segmentIds === []) {
            return 0;
        }

        $now = Carbon::now();
        $rows = [];
        foreach ($segmentIds as $sid) {
            foreach ($userIds as $uid) {
                $rows[] = [
                    'segment_id' => $sid,
                    'user_id' => $uid,
                    'source' => $source,
                    'created_at' => $now,
                ];
            }
        }

        return DB::table('user_segment_memberships')->insertOrIgnore($rows);
    }

    /**
     * 笛卡尔批量解标
     *
     * @param  array<int, int>  $segmentIds
     * @param  array<int, int>  $userIds
     * @return int 删除行数
     */
    public function batchDetach(array $segmentIds, array $userIds): int
    {
        $this->assertBatchSize($segmentIds);
        $this->assertBatchSize($userIds);

        if ($userIds === [] || $segmentIds === []) {
            return 0;
        }

        return Membership::query()
            ->whereIn('segment_id', $segmentIds)
            ->whereIn('user_id', $userIds)
            ->delete();
    }

    /**
     * 同步单个规则分群（增量 diff）
     *
     *  1. 用 Evaluator 编译 rule → 闭包，查询匹配 user_id 集合
     *  2. 查现有 (segment_id=?) 下的 user_id 集合
     *  3. diff：需要新增的 / 需要移除的
     *  4. 批量 insertOrIgnore / delete（限定 source=RULE，避免误删手动打标）
     *  5. 写回 last_synced_at / last_synced_user_count
     *
     * @return array{added:int, removed:int, total:int}
     */
    public function syncRuleSegment(Segment $segment): array
    {
        if (! $segment->type->isRule()) {
            throw new InvalidArgumentException('仅规则分群支持同步');
        }

        if (! $segment->status) {
            throw new InvalidArgumentException('禁用的分群不允许同步');
        }

        $apply = $this->evaluator->compile($segment->rule);
        $matchedQuery = User::query();
        $apply($matchedQuery);
        $matchedIds = $matchedQuery->pluck('id')->all();

        return $this->applyDiff($segment, $matchedIds);
    }

    /**
     * 预览规则匹配结果（不写库）
     *
     * @return array{total:int, sample:array<int, int>}
     */
    public function previewRuleSegment(Segment $segment, int $sampleSize = 5): array
    {
        if (! $segment->type->isRule()) {
            throw new InvalidArgumentException('仅规则分群支持预览');
        }

        $apply = $this->evaluator->compile($segment->rule);
        $query = User::query();
        $apply($query);

        $total = (clone $query)->count();
        $sample = $query->limit(max(1, $sampleSize))->pluck('id')->all();

        return [
            'total' => $total,
            'sample' => $sample,
        ];
    }

    /**
     * 应用 diff：把 matchedIds 写为该分群的 RULE 来源成员
     *
     * @param  array<int, int>  $matchedIds
     * @return array{added:int, removed:int, total:int}
     */
    protected function applyDiff(Segment $segment, array $matchedIds): array
    {
        $matchedSet = array_flip($matchedIds);

        // 现有 RULE 来源成员
        $existing = Membership::query()
            ->where('segment_id', $segment->id)
            ->where('source', Membership::SOURCE_RULE)
            ->pluck('user_id')
            ->all();
        $existingSet = array_flip($existing);

        $toAdd = array_keys(array_diff_key($matchedSet, $existingSet));
        $toRemove = array_keys(array_diff_key($existingSet, $matchedSet));

        // 移除：分批删除避免大事务
        $removed = 0;
        foreach (array_chunk($toRemove, self::BATCH_LIMIT) as $chunk) {
            $removed += Membership::query()
                ->where('segment_id', $segment->id)
                ->where('source', Membership::SOURCE_RULE)
                ->whereIn('user_id', $chunk)
                ->delete();
        }

        // 新增：insertOrIgnore 走唯一键
        $added = 0;
        if ($toAdd !== []) {
            $userIds = $this->filterExistingUserIds($toAdd);
            $now = Carbon::now();
            foreach (array_chunk($userIds, self::BATCH_LIMIT) as $chunk) {
                $rows = array_map(fn (int $uid) => [
                    'segment_id' => $segment->id,
                    'user_id' => $uid,
                    'source' => Membership::SOURCE_RULE,
                    'created_at' => $now,
                ], $chunk);
                $added += DB::table('user_segment_memberships')->insertOrIgnore($rows);
            }
        }

        $segment->forceFill([
            'last_synced_at' => Carbon::now(),
            'last_synced_user_count' => count($matchedIds),
        ])->save();

        return [
            'added' => $added,
            'removed' => $removed,
            'total' => count($matchedIds),
        ];
    }

    /**
     * 过滤掉已删除 / 不存在的用户 ID
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function filterExistingUserIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $ids))))
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * 批量打标/解标上限校验
     *
     * @param  array<mixed>  $ids
     */
    protected function assertBatchSize(array $ids): void
    {
        if (count($ids) > self::BATCH_LIMIT) {
            throw new InvalidArgumentException(
                '单次批量操作不能超过 '.self::BATCH_LIMIT.' 个 ID'
            );
        }
    }
}
