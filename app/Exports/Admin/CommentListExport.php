<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Exports\AbstractExport;
use App\Models\Content\Comment;
use Illuminate\Database\Eloquent\Builder;

/**
 * 后台评论管理列表导出
 */
class CommentListExport extends AbstractExport
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
            'user_id' => '用户ID',
            'source_type' => '源类型',
            'source_id' => '源ID',
            'is_top' => '是否置顶',
            'status' => '状态',
            'content' => '评论内容',
            'like_count' => '点赞数',
            'comment_count' => '回复数',
            'ip_address' => 'IP',
            'created_at' => '评论时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        $q = Comment::query()->orderByDesc('id');

        if ($status = $this->filters['status'] ?? null) {
            $q->where('status', $status);
        }
        if ($sourceType = $this->filters['source_type'] ?? null) {
            $q->where('source_type', $sourceType);
        }
        if ($keyword = $this->filters['keyword'] ?? null) {
            $q->where('content', 'like', "%{$keyword}%");
        }
        if ($userId = $this->filters['user_id'] ?? null) {
            $q->where('user_id', (int) $userId);
        }

        return $q;
    }

    protected function sheetTitle(): string
    {
        return '评论列表';
    }

    public function map(mixed $row): array
    {
        $result = [];
        foreach (array_keys($this->columns()) as $field) {
            $value = data_get($row, $field);
            if ($field === 'status') {
                $value = $this->statusLabel($value);
            } elseif ($field === 'is_top') {
                $value = $value ? '是' : '否';
            }
            $result[] = $this->normalize($value, $field);
        }

        return $result;
    }

    private function statusLabel(mixed $status): string
    {
        if ($status instanceof \App\Enums\ReviewStatus) {
            return $status->label();
        }

        return match ((string) $status) {
            'pending' => '待审核',
            'approved' => '已通过',
            'rejected' => '已驳回',
            default => (string) $status,
        };
    }
}
