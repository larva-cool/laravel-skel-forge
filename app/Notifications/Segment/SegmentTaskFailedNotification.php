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
 * 分群异步任务失败通知
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentTaskFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $taskId,
        public readonly string $taskType,
        public readonly string $reason,
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
            'title' => '分群任务失败',
            'body' => "{$typeLabel}任务失败：{$this->reason}",
            'task_id' => $this->taskId,
            'task_type' => $this->taskType,
            'reason' => $this->reason,
            'type' => 'segment.task.failed',
        ]);
    }
}
