<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Exports\AbstractExport;
use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * 后台审计日志导出
 */
class AuditLogListExport extends AbstractExport
{
    public const SYNC_THRESHOLD = 1000;

    public function __construct(private readonly array $filters = []) {}

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => 'ID',
            'operator_id' => '操作人ID',
            'operator_name' => '操作人',
            'route' => '路由',
            'method' => '方法',
            'uri' => '请求地址',
            'title' => '操作描述',
            'status_code' => '状态码',
            'runtime' => '耗时(秒)',
            'ip' => 'IP',
            'created_at' => '操作时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        $q = AuditLog::query()->orderByDesc('id');

        if (! empty($this->filters['operator_id'])) {
            $q->where('operator_id', (int) $this->filters['operator_id']);
        }
        if (! empty($this->filters['operator_name'])) {
            $q->where('operator_name', 'like', '%'.$this->filters['operator_name'].'%');
        }
        if (! empty($this->filters['route'])) {
            $q->where('route', 'like', '%'.$this->filters['route'].'%');
        }
        if (! empty($this->filters['method'])) {
            $q->where('method', strtoupper((string) $this->filters['method']));
        }
        if (! empty($this->filters['uri'])) {
            $q->where('uri', 'like', '%'.$this->filters['uri'].'%');
        }
        if (! empty($this->filters['status_code'])) {
            $q->where('status_code', (int) $this->filters['status_code']);
        }
        if (isset($this->filters['is_failed']) && $this->filters['is_failed'] !== '' && $this->filters['is_failed'] !== null) {
            if (filter_var($this->filters['is_failed'], FILTER_VALIDATE_BOOLEAN)) {
                $q->where('status_code', '>=', 400);
            } else {
                $q->where('status_code', '<', 400);
            }
        }
        if (! empty($this->filters['start_date'])) {
            $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($this->filters['start_date'])->startOfDay());
        }
        if (! empty($this->filters['end_date'])) {
            $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($this->filters['end_date'])->endOfDay());
        }

        return $q;
    }

    protected function sheetTitle(): string
    {
        return '审计日志';
    }
}
