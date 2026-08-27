<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports;

use App\Exports\Admin\AuditLogListExport;
use App\Exports\Admin\CoinTradeExport;
use App\Exports\Admin\CommentListExport;
use App\Exports\Admin\FeedbackListExport;
use App\Exports\Admin\PointTradeExport;
use App\Exports\Admin\UserListExport;
use App\Jobs\Export\PerformExportJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 后台数据导出共享逻辑（trait）
 *
 * 在需要使用数据导出的 Admin 控制器中 use 此 trait。
 */
trait HandlesExports
{
    /**
     * 同步导出（行数 ≤ 阈值）
     */
    protected function exportSync(
        string $exportClass,
        array $exportArgs,
        string $fileName,
    ): BinaryFileResponse {
        /** @var AbstractExport $export */
        $export = new $exportClass(...$exportArgs);

        return $export->download($fileName, ExcelWriterType::XLSX);
    }

    /**
     * 异步导出（行数 > 阈值）
     */
    protected function exportAsync(
        Request $request,
        string $exportClass,
        array $exportArgs,
        string $fileName,
    ): \Illuminate\Http\JsonResponse {
        $admin = $request->user('admin');
        if (! $admin) {
            abort(401);
        }

        $date = Carbon::now()->format('Ymd_His');
        $relativePath = "exports/admin-{$admin->id}/{$date}_{$fileName}";

        PerformExportJob::dispatch(
            exportClass: $exportClass,
            exportArgs: $exportArgs,
            relativePath: $relativePath,
            fileName: $fileName,
            adminId: $admin->id,
        )->onQueue('exports');

        return response()->json([
            'message' => '导出任务已提交，完成后将通过站内信通知您下载。',
            'file_name' => $fileName,
        ], 202);
    }

    /**
     * 通用调度：≤ 阈值同步导出，否则异步
     */
    protected function exportDispatch(
        Request $request,
        string $exportClass,
        array $exportArgs,
        string $fileName,
        int $threshold,
    ): BinaryFileResponse|\Illuminate\Http\JsonResponse {
        $count = $this->estimateExportCount($exportClass, $exportArgs);

        if ($count <= $threshold) {
            return $this->exportSync($exportClass, $exportArgs, $fileName);
        }

        return $this->exportAsync($request, $exportClass, $exportArgs, $fileName);
    }

    /**
     * 估算 Export 命中的行数
     */
    protected function estimateExportCount(string $exportClass, array $exportArgs): int
    {
        try {
            /** @var AbstractExport $export */
            $export = new $exportClass(...$exportArgs);
            $query = $export->query();
            $count = (clone $query)->getQuery()->getCountForPagination();

            return (int) $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    // ========== 筛选参数提取器 ==========

    protected function userListFilters(FormRequest|Request $request): array
    {
        return [
            'keyword' => $request->query('keyword'),
            'status' => $request->query('status'),
            'vip' => $request->query('vip'),
            'login_start' => $request->query('login_start'),
            'login_end' => $request->query('login_end'),
            'register_start' => $request->query('register_start'),
            'register_end' => $request->query('register_end'),
        ];
    }

    protected function commentListFilters(FormRequest|Request $request): array
    {
        return [
            'status' => $request->query('status'),
            'source_type' => $request->query('source_type'),
            'keyword' => $request->query('keyword'),
            'user_id' => $request->query('user_id'),
        ];
    }

    protected function feedbackListFilters(FormRequest|Request $request): array
    {
        return [
            'keyword' => $request->query('keyword'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ];
    }

    protected function auditLogFilters(FormRequest|Request $request): array
    {
        return [
            'operator_id' => $request->query('operator_id'),
            'operator_name' => $request->query('operator_name'),
            'route' => $request->query('route'),
            'method' => $request->query('method'),
            'uri' => $request->query('uri'),
            'status_code' => $request->query('status_code'),
            'is_failed' => $request->query('is_failed'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];
    }

    protected function tradeListFilters(FormRequest|Request $request): array
    {
        return [
            'type' => $request->query('type'),
            'keyword' => $request->query('keyword'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];
    }

    // ========== 工具方法 ==========

    protected function exportFileName(string $prefix, string $ext = 'xlsx'): string
    {
        return $prefix.'_'.Carbon::now()->format('Ymd_His').'.'.$ext;
    }

    protected function userExportClass(): string
    {
        return UserListExport::class;
    }

    protected function commentExportClass(): string
    {
        return CommentListExport::class;
    }

    protected function feedbackExportClass(): string
    {
        return FeedbackListExport::class;
    }

    protected function auditLogExportClass(): string
    {
        return AuditLogListExport::class;
    }

    protected function pointTradeExportClass(): string
    {
        return PointTradeExport::class;
    }

    protected function coinTradeExportClass(): string
    {
        return CoinTradeExport::class;
    }
}
