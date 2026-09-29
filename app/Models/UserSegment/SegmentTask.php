<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\UserSegment;

use App\Enums\SegmentTaskStatus;
use App\Enums\SegmentTaskType;
use App\Models\Admin\Admin;
use App\Models\Model;
use Database\Factories\UserSegment\SegmentTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 分群异步任务模型
 *
 * 跟踪 > BATCH_LIMIT 的批量打标/解标任务进度。
 * 通过任务 ID 轮询状态；完成后通过站内信通知提交管理员。
 *
 * @property int $id 任务 ID
 * @property int $admin_id 提交任务的管理员 ID
 * @property SegmentTaskType $type 任务类型
 * @property array<string,mixed> $payload 任务载荷（segment_ids / user_ids）
 * @property int $total_count 计划处理总数
 * @property int $processed_count 已处理数
 * @property int $added_count 实际新增/解绑数
 * @property SegmentTaskStatus $status 任务状态
 * @property string|null $error_message 失败原因
 * @property Carbon|null $started_at 开始时间
 * @property Carbon|null $finished_at 完成时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系
 * @property-read Admin $admin
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('segment_tasks')]
#[Fillable([
    'admin_id', 'type', 'payload', 'total_count', 'processed_count',
    'added_count', 'status', 'error_message', 'started_at', 'finished_at',
])]
class SegmentTask extends Model
{
    /** @use HasFactory<SegmentTaskFactory> */
    use HasFactory;
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'admin_id' => 'integer',
            'type' => SegmentTaskType::class,
            'payload' => 'array',
            'total_count' => 'integer',
            'processed_count' => 'integer',
            'added_count' => 'integer',
            'status' => SegmentTaskStatus::class,
            'error_message' => 'string',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 提交任务的管理员
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 进度百分比（0-100）
     */
    public function progressPercent(): int
    {
        if ($this->total_count === 0) {
            return 0;
        }

        return (int) min(100, round($this->processed_count / $this->total_count * 100));
    }

    /**
     * 标记为执行中
     */
    public function markRunning(): void
    {
        $this->forceFill([
            'status' => SegmentTaskStatus::RUNNING,
            'started_at' => $this->started_at ?? Carbon::now(),
        ])->save();
    }

    /**
     * 累加进度
     */
    public function incrementProgress(int $processed, int $added): void
    {
        $this->forceFill([
            'processed_count' => $this->processed_count + $processed,
            'added_count' => $this->added_count + $added,
        ])->save();
    }

    /**
     * 标记为完成
     */
    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => SegmentTaskStatus::COMPLETED,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    /**
     * 标记为失败
     */
    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => SegmentTaskStatus::FAILED,
            'error_message' => mb_substr($reason, 0, 1000),
            'finished_at' => Carbon::now(),
        ])->save();
    }
}
