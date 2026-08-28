<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Segment;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * 分群异步任务完成通知
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentTaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $taskId,
        public readonly string $taskType,
        public readonly int $totalCount,
        public readonly int $addedCount,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): DatabaseMessage
    {
        $typeLabel = $this->taskType === 'attach' ? '批量打标' : '批量解标';

        return new DatabaseMessage([
            'title' => '分群任务完成',
            'body' => "{$typeLabel}任务已完成：处理 {$this->totalCount} 个用户，实际生效 {$this->addedCount} 个",
            'task_id' => $this->taskId,
            'task_type' => $this->taskType,
            'total_count' => $this->totalCount,
            'added_count' => $this->addedCount,
            'type' => 'segment.task.completed',
        ]);
    }
}
