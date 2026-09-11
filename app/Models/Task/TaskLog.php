<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Task;

use App\Enums\Task\TaskRewardType;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 任务完成/奖励发放日志
 *
 * @property int $id 日志ID
 * @property int $user_id 用户ID
 * @property int $task_id 任务ID
 * @property string $period_key 周期标识
 * @property TaskRewardType $reward_type 奖励类型
 * @property int $reward_amount 奖励数量
 * @property string|null $source_type 触发来源类型
 * @property string|null $remark 备注
 * @property Carbon $created_at
 * @property-read Task $task
 * @property-read User $user
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('task_logs')]
#[Fillable(['user_id', 'task_id', 'period_key', 'reward_type', 'reward_amount', 'source_type', 'remark'])]
class TaskLog extends Model
{
    public const UPDATED_AT = null;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'task_id' => 'integer',
            'period_key' => 'string',
            'reward_type' => TaskRewardType::class,
            'reward_amount' => 'integer',
            'source_type' => 'string',
            'remark' => 'string',
            'created_at' => 'datetime',
        ];
    }
}
