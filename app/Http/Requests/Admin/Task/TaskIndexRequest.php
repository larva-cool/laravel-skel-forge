<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Task;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read string|null $keyword 关键词搜索
 * @property-read int|null $group_id 分组筛选
 * @property-read string|null $type 任务类型
 * @property-read string|null $status 状态筛选
 */
class TaskIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'group_id' => ['sometimes', 'integer', 'exists:task_groups,id'],
            'type' => ['sometimes', 'string'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
