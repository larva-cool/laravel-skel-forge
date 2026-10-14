<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Task;

use App\Enums\Task\TaskClaimMode;
use App\Enums\Task\TaskRewardType;
use App\Enums\Task\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property-read int $group_id 所属分组ID
 * @property-read string $name 任务名称
 * @property-read string $code 任务编码
 * @property-read TaskType $type 任务类型
 * @property-read string|null $description 任务描述
 * @property-read array $condition 任务条件
 * @property-read TaskRewardType $reward_type 奖励类型
 * @property-read int $reward_amount 奖励数量
 * @property-read TaskClaimMode $claim_mode 领取方式
 * @property-read int $sort_order 排序值
 * @property-read string|null $start_at 生效开始时间
 * @property-read string|null $end_at 生效结束时间
 * @property-read bool $status 是否启用
 */
class TaskSaveRequest extends FormRequest
{
    public function rules(): array
    {
        $taskId = $this->route('task');

        return [
            'group_id' => ['required', 'integer', 'exists:task_groups,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', Rule::unique('tasks', 'code')->ignore($taskId)],
            'type' => ['required', 'string', Rule::enum(TaskType::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'condition' => ['sometimes', 'array'],
            'condition.serial_days' => ['sometimes', 'integer', 'min:0'],
            'condition.target_value' => ['sometimes', 'integer', 'min:0'],
            'condition.completeness_threshold' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'reward_type' => ['required', 'string', Rule::enum(TaskRewardType::class)],
            'reward_amount' => ['required', 'integer', 'min:0'],
            'claim_mode' => ['required', 'integer', Rule::enum(TaskClaimMode::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'start_at' => ['sometimes', 'nullable', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after:start_at'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
