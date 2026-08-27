<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Export;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * 异步导出失败通知
 */
class ExportFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $fileName,
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
        return new DatabaseMessage([
            'title' => '导出失败',
            'body' => "文件「{$this->fileName}」生成失败：{$this->reason}",
            'file_name' => $this->fileName,
            'reason' => $this->reason,
            'type' => 'export.failed',
        ]);
    }
}
