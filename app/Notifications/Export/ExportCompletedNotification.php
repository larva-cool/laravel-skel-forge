<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Export;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * 异步导出完成通知
 */
class ExportCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $fileName,
        public readonly string $relativePath,
        public readonly string $disk = 'public',
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
        $url = $this->resolveUrl();

        return new DatabaseMessage([
            'title' => '导出完成',
            'body' => $url === null
                ? "文件「{$this->fileName}」已生成，请前往附件管理下载"
                : "文件「{$this->fileName}」已生成，[点击下载]({$url})",
            'url' => $url,
            'file_name' => $this->fileName,
            'relative_path' => $this->relativePath,
            'disk' => $this->disk,
            'type' => 'export.completed',
        ]);
    }

    /**
     * 解析下载地址
     *
     * 公开磁盘直接返回永久 URL；私有磁盘签发临时地址（有效期取 upload.temporary_url_minutes）。
     * 两者均不可用时返回 null，由调用方提示改走其他下载途径。
     */
    protected function resolveUrl(): ?string
    {
        $disk = Storage::disk($this->disk);

        if ($this->isPublicDisk()) {
            try {
                return $disk->url($this->relativePath);
            } catch (\Throwable) {
                // 继续尝试临时地址
            }
        }

        $minutes = (int) settings('upload.temporary_url_minutes', 10);

        try {
            return $disk->temporaryUrl($this->relativePath, Carbon::now()->addMinutes($minutes));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 判断磁盘是否公开可访问（与 AttachmentService 保持同一判定标准）
     */
    protected function isPublicDisk(): bool
    {
        return config("filesystems.disks.{$this->disk}.visibility") === 'public'
            || config("filesystems.disks.{$this->disk}.url") !== null;
    }
}
