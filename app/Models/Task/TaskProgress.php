<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Task;

use App\Enums\Task\TaskProgressStatus;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 用户任务进度
 *
 * @property int $id 进度ID
 * @property int $user_id 用户ID
 * @property int $task_id 任务ID
 * @property string $period_key 周期标识
 * @property int $current_value 当前进度值
 * @property TaskProgressStatus $status 状态
 * @property Carbon|null $completed_at 完成时间
 * @property Carbon|null $rewarded_at 奖励发放时间
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Task $task
 * @property-read User $user
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('task_progress')]
#[Fillable([
    'user_id', 'task_id', 'period_key', 'current_value', 'status',
    'completed_at', 'rewarded_at',
])]
class TaskProgress extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 是否已完成（含已领取状态）
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [
            TaskProgressStatus::COMPLETED,
            TaskProgressStatus::REWARDED,
        ], true);
    }

    /**
     * 是否已领取奖励
     */
    public function isRewarded(): bool
    {
        return $this->status === TaskProgressStatus::REWARDED;
    }

    /**
     * 标记为已完成
     */
    public function markCompleted(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $this->status = TaskProgressStatus::COMPLETED;
        $this->completed_at = now();

        return $this->save();
    }

    /**
     * 标记为已领取奖励
     */
    public function markRewarded(): bool
    {
        $this->status = TaskProgressStatus::REWARDED;
        $this->rewarded_at = now();

        return $this->save();
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'task_id' => 'integer',
            'period_key' => 'string',
            'current_value' => 'integer',
            'status' => TaskProgressStatus::class,
            'completed_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
