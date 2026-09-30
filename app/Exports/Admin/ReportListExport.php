<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Enums\ReportStatus;
use App\Exports\AbstractExport;
use App\Models\Content\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 后台举报列表导出
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportListExport extends AbstractExport
{
    public const SYNC_THRESHOLD = 1000;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => 'ID',
            'user_id' => '举报人ID',
            'reportable_type' => '被举报对象类型',
            'reportable_id' => '被举报对象ID',
            'category' => '举报分类',
            'reason' => '举报描述',
            'status' => '处理状态',
            'handler_id' => '处理人ID',
            'process_remark' => '处理备注',
            'processed_at' => '处理时间',
            'created_at' => '举报时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        return app(\App\Services\ReportService::class)
            ->listQuery($this->filters);
    }

    protected function sheetTitle(): string
    {
        return '举报列表';
    }

    public function map(mixed $row): array
    {
        $result = [];
        foreach (array_keys($this->columns()) as $field) {
            $value = data_get($row, $field);
            $value = match ($field) {
                'reportable_type' => $this->humanType($value),
                'category' => $this->categoryLabel($value),
                'status' => $this->statusLabel($value),
                default => $value,
            };
            $result[] = $this->normalize($value, $field);
        }

        return $result;
    }

    private function humanType(?string $type): string
    {
        if ($type === Comment::class || str_ends_with((string) $type, '\\Comment')) {
            return '评论';
        }
        if ($type === User::class || str_ends_with((string) $type, '\\User')) {
            return '用户';
        }

        return (string) ($type ?? '');
    }

    private function categoryLabel(?string $value): string
    {
        $map = \App\Http\Resources\Admin\ReportResource::getCategoryMap();

        return $map[$value]['label'] ?? (string) $value;
    }

    private function statusLabel(mixed $status): string
    {
        if ($status instanceof ReportStatus) {
            return $status->label();
        }

        return match ((string) $status) {
            'pending' => '待处理',
            'processing' => '处理中',
            'resolved' => '已处理',
            'rejected' => '已驳回',
            default => (string) $status,
        };
    }
}
