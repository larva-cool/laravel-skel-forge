<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Exports\AbstractExport;
use App\Models\System\Feedback;
use Illuminate\Database\Eloquent\Builder;

/**
 * 后台反馈列表导出
 */
class FeedbackListExport extends AbstractExport
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
            'code' => '编号',
            'title' => '标题',
            'type' => '类型',
            'status' => '状态',
            'priority' => '优先级',
            'contact_name' => '联系人',
            'contact_info' => '联系方式',
            'user_id' => '用户ID',
            'admin_id' => '处理人ID',
            'ip_address' => '提交IP',
            'resolved_at' => '解决时间',
            'closed_at' => '关闭时间',
            'created_at' => '创建时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        $q = Feedback::query()->orderByDesc('id');

        if ($keyword = $this->filters['keyword'] ?? null) {
            $like = "%{$keyword}%";
            $q->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('content', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_info', 'like', $like);
            });
        }
        if ($type = $this->filters['type'] ?? null) {
            $q->where('type', $type);
        }
        if ($status = $this->filters['status'] ?? null) {
            $q->where('status', $status);
        }
        if ($priority = $this->filters['priority'] ?? null) {
            $q->where('priority', $priority);
        }

        return $q;
    }

    protected function sheetTitle(): string
    {
        return '反馈列表';
    }

    public function map(mixed $row): array
    {
        $result = [];
        foreach (array_keys($this->columns()) as $field) {
            $value = data_get($row, $field);
            if ($field === 'status') {
                $value = $this->statusLabel($value);
            } elseif ($field === 'priority') {
                $value = $this->priorityLabel($value);
            }
            $result[] = $this->normalize($value, $field);
        }

        return $result;
    }

    private function statusLabel(mixed $status): string
    {
        if ($status instanceof FeedbackStatus) {
            return $status->label();
        }

        return match ((string) $status) {
            'pending' => '待处理',
            'analyzing' => '分析中',
            'planned' => '已规划',
            'processing' => '处理中',
            'resolved' => '已解决',
            'closed' => '已关闭',
            default => (string) $status,
        };
    }

    private function priorityLabel(mixed $priority): string
    {
        if ($priority instanceof FeedbackPriority) {
            return $priority->label();
        }

        return match ((string) $priority) {
            'low' => '低',
            'normal' => '普通',
            'high' => '高',
            'urgent' => '紧急',
            default => (string) $priority,
        };
    }
}
