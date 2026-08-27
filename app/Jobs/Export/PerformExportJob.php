<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\Export;

use App\Models\Admin\Admin;
use App\Notifications\Export\ExportCompletedNotification;
use App\Notifications\Export\ExportFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * 异步导出 Excel 任务
 *
 * 通过 ::store() 写入 public disk，再向请求管理员推送通知。
 */
class PerformExportJob implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800; // 30 分钟

    /**
     * @param  class-string  $exportClass  Export 类
     * @param  array<string, mixed>  $exportArgs  Export 构造函数参数（必须为数组）
     * @param  string  $fileName  下载文件名（含扩展名）
     */
    public function __construct(
        public readonly string $exportClass,
        public readonly array $exportArgs,
        public readonly string $relativePath,
        public readonly string $fileName,
        public readonly int $adminId,
    ) {}

    public function handle(): void
    {
        $disk = settings('upload.storage', 'public');

        try {
            /** @var \Maatwebsite\Excel\Concerns\FromQuery&\Illuminate\Support\Responsable $export */
            $export = new $this->exportClass(...$this->exportArgs);

            Excel::store(
                $export,
                $this->relativePath,
                disk: $disk,
                writerType: ExcelWriterType::XLSX
            );

            $this->notifyAdmin(new ExportCompletedNotification(
                fileName: $this->fileName,
                relativePath: $this->relativePath,
                disk: $disk,
            ));
        } catch (Throwable $e) {
            Log::error('异步导出失败', [
                'export' => $this->exportClass,
                'admin_id' => $this->adminId,
                'disk' => $disk,
                'file' => $this->fileName,
                'error' => $e->getMessage(),
            ]);
            $this->notifyAdmin(new ExportFailedNotification(
                fileName: $this->fileName,
                reason: $e->getMessage(),
            ));

            throw $e;
        }
    }

    /**
     * 任务失败回调（达到最大重试后）
     */
    public function failed(Throwable $e): void
    {
        Log::error('异步导出最终失败', [
            'export' => $this->exportClass,
            'admin_id' => $this->adminId,
            'error' => $e->getMessage(),
        ]);
    }

    private function notifyAdmin(object $notification): void
    {
        $admin = Admin::query()->find($this->adminId);
        if ($admin) {
            Notification::send($admin, $notification);
        }
    }
}
