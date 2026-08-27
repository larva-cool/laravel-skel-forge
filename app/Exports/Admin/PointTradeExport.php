<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Exports\AbstractExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 用户积分流水导出（单用户）
 */
class PointTradeExport extends AbstractExport
{
    public const SYNC_THRESHOLD = 2000;

    public function __construct(
        public readonly User $user,
        private readonly array $filters = [],
    ) {}

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '流水号',
            'user_id' => '用户ID',
            'points' => '积分数',
            'description' => '说明',
            'type' => '类型',
            'source_type' => '来源类型',
            'source_id' => '来源ID',
            'expired_at' => '过期时间',
            'created_at' => '时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        $q = $this->user->points()->getQuery()->orderByDesc('id');

        if ($type = $this->filters['type'] ?? null) {
            $q->where('type', $type);
        }
        if ($keyword = $this->filters['keyword'] ?? null) {
            $q->where('description', 'like', "%{$keyword}%");
        }
        if ($start = $this->filters['start_date'] ?? null) {
            $q->whereDate('created_at', '>=', $start);
        }
        if ($end = $this->filters['end_date'] ?? null) {
            $q->whereDate('created_at', '<=', $end);
        }

        return $q;
    }

    protected function sheetTitle(): string
    {
        return '积分流水';
    }
}
