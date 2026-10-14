<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\TaskGroup;

use App\Enums\Task\TaskGroupType;
use App\Enums\Task\TaskResetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property-read string $name 分组名称
 * @property-read string $code 分组编码
 * @property-read TaskGroupType $type 分组类型
 * @property-read TaskResetType $reset_type 重置类型
 * @property-read int $sort_order 排序值
 * @property-read string|null $icon 图标
 * @property-read string|null $description 描述
 * @property-read bool $status 是否启用
 */
class TaskGroupSaveRequest extends FormRequest
{
    public function rules(): array
    {
        $groupId = $this->route('taskGroup');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', Rule::unique('task_groups', 'code')->ignore($groupId)],
            'type' => ['required', 'integer', Rule::enum(TaskGroupType::class)],
            'reset_type' => ['required', 'integer', Rule::enum(TaskResetType::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
