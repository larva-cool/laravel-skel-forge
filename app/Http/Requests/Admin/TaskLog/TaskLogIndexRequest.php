<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\TaskLog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 任务日志列表查询参数
 *
 * @property-read string|null $keyword 关键词（任务名称/用户名/用户ID）
 * @property-read int|null $task_id 任务筛选
 * @property-read string|null $reward_type 奖励类型筛选
 * @property-read string|null $start_date 开始日期（Y-m-d）
 * @property-read string|null $end_date 结束日期（Y-m-d）
 */
class TaskLogIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'task_id' => ['sometimes', 'integer', 'exists:tasks,id'],
            'reward_type' => ['sometimes', 'string'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
