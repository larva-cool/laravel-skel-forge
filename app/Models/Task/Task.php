<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Task;

use App\Casts\AsTaskCondition;
use App\Enums\Task\TaskClaimMode;
use App\Enums\Task\TaskRewardType;
use App\Enums\Task\TaskType;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 任务定义
 *
 * @property int $id 任务ID
 * @property int $group_id 所属分组ID
 * @property string $name 任务名称
 * @property string $code 任务编码
 * @property TaskType $type 任务类型
 * @property string|null $description 任务描述
 * @property array $condition 任务条件（{serial_days, target_value, completeness_threshold}）
 * @property TaskRewardType $reward_type 奖励类型
 * @property int $reward_amount 奖励数量
 * @property TaskClaimMode $claim_mode 领取方式
 * @property int $sort_order 排序值
 * @property Carbon|null $start_at 生效开始时间
 * @property Carbon|null $end_at 生效结束时间
 * @property bool $status 是否启用
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TaskGroup $group
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('tasks')]
#[Fillable([
    'group_id', 'name', 'code', 'type', 'description', 'condition',
    'reward_type', 'reward_amount', 'claim_mode', 'sort_order',
    'start_at', 'end_at', 'status',
])]
class Task extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TaskLog::class);
    }

    /**
     * 判断任务当前是否在有效时间内
     */
    public function isActive(): bool
    {
        if (! $this->status) {
            return false;
        }
        if ($this->start_at && $this->start_at->isFuture()) {
            return false;
        }
        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * 判断是否为自动发放奖励
     */
    public function isAutoClaim(): bool
    {
        return $this->claim_mode === TaskClaimMode::AUTO;
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'group_id' => 'integer',
            'name' => 'string',
            'code' => 'string',
            'type' => TaskType::class,
            'description' => 'string',
            'condition' => AsTaskCondition::class,
            'reward_type' => TaskRewardType::class,
            'reward_amount' => 'integer',
            'claim_mode' => TaskClaimMode::class,
            'sort_order' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
