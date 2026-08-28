<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\Segment;

use App\Enums\SegmentTaskStatus;
use App\Enums\SegmentTaskType;
use App\Models\Admin\Admin;
use App\Models\UserSegment\Segment;
use App\Models\UserSegment\SegmentTask;
use App\Notifications\Segment\SegmentTaskCompletedNotification;
use App\Notifications\Segment\SegmentTaskFailedNotification;
use App\Services\Segment\SegmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * 异步分群打标/解标任务
 *
 * 接收 segment_ids × user_ids 笛卡尔积，按 BATCH_LIMIT 分批处理；
 * 实时更新 SegmentTask 进度；完成后通过站内信通知提交管理员。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BulkSegmentTaskJob implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** 单次任务最大执行时间（10 分钟） */
    public int $timeout = 600;

    public function __construct(
        public readonly int $taskId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SegmentService $service): void
    {
        $task = SegmentTask::query()->find($this->taskId);
        if (! $task) {
            Log::warning('SegmentTask 不存在', ['task_id' => $this->taskId]);

            return;
        }

        // 幂等：已开始/已完成的任务直接跳过
        if ($task->status !== SegmentTaskStatus::PENDING) {
            return;
        }

        $task->markRunning();

        $payload = $task->payload ?? [];
        $segmentIds = array_values(array_map('intval', $payload['segment_ids'] ?? []));
        $userIds = array_values(array_map('intval', $payload['user_ids'] ?? []));
        $type = $task->type;

        $totalAdded = 0;
        $processed = 0;

        try {
            if ($type === SegmentTaskType::ATTACH) {
                foreach ($segmentIds as $segmentId) {
                    $segment = Segment::query()->find($segmentId);
                    if (! $segment) {
                        continue;
                    }
                    // 复用现有 chunk 逻辑：>BATCH_LIMIT 自动分块
                    foreach (array_chunk($userIds, SegmentService::BATCH_LIMIT) as $chunk) {
                        $added = $service->attachUsers($segment, $chunk, \App\Models\UserSegment\Membership::SOURCE_MANUAL);
                        $totalAdded += $added;
                        $processed += count($chunk);
                        $task->incrementProgress(count($chunk), $added);
                    }
                }
            } else {
                // DETACH
                foreach ($segmentIds as $segmentId) {
                    $segment = Segment::query()->find($segmentId);
                    if (! $segment) {
                        continue;
                    }
                    foreach (array_chunk($userIds, SegmentService::BATCH_LIMIT) as $chunk) {
                        $removed = $service->detachUsers($segment, $chunk);
                        $totalAdded += $removed;
                        $processed += count($chunk);
                        $task->incrementProgress(count($chunk), $removed);
                    }
                }
            }

            $task->markCompleted();

            $this->notifyAdmin(new SegmentTaskCompletedNotification(
                taskId: $task->id,
                taskType: $type->value,
                totalCount: $task->total_count,
                addedCount: $totalAdded,
            ));
        } catch (Throwable $e) {
            Log::error('SegmentTask 执行失败', [
                'task_id' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
            $task->markFailed($e->getMessage());
            $this->notifyAdmin(new SegmentTaskFailedNotification(
                taskId: $task->id,
                taskType: $type->value,
                reason: $e->getMessage(),
            ));

            throw $e;
        }
    }

    /**
     * 任务最终失败回调（达到最大重试后）
     */
    public function failed(Throwable $e): void
    {
        Log::error('SegmentTask 最终失败', [
            'task_id' => $this->taskId,
            'error' => $e->getMessage(),
        ]);
    }

    private function notifyAdmin(object $notification): void
    {
        $task = SegmentTask::query()->find($this->taskId);
        if (! $task) {
            return;
        }
        $admin = Admin::query()->find($task->admin_id);
        if ($admin) {
            Notification::send($admin, $notification);
        }
    }
}
