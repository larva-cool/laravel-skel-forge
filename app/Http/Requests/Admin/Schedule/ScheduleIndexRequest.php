<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Schedule;

use App\Enums\ScheduleTaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 定时任务列表请求
 *
 * @property-read string|null $name 任务名称关键词
 * @property-read string|null $command 命令关键词
 * @property-read int|null $status 状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'command' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in(ScheduleTaskStatus::values())],
        ];
    }

    /**
     * 获取列表筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only(['name', 'command', 'status']);
    }
}
