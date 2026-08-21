<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\ScheduleLog;

use App\Enums\ScheduleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 调度日志列表请求
 *
 * @property-read string|null $name 任务名称关键词
 * @property-read string|null $type 任务类型
 * @property-read int|null $status 执行状态
 * @property-read string|null $start_date 开始时间起始
 * @property-read string|null $end_date 开始时间截止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleLogIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['command', 'callback', 'exec'])],
            'status' => ['nullable', 'integer', Rule::in(ScheduleStatus::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * 获取列表筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only(['name', 'type', 'status', 'start_date', 'end_date']);
    }
}
