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
 * 定时任务保存请求（创建/编辑）
 *
 * @property-read string $name 任务名称
 * @property-read string $command Artisan 命令名或 custom
 * @property-read string|null $command_custom 自定义 Shell 命令
 * @property-read array|null $params 命令参数
 * @property-read array|null $options 命令选项
 * @property-read array|null $options_with_value 带值命令选项
 * @property-read string $expression Cron 表达式
 * @property-read array|null $environments 执行环境限定
 * @property-read string|null $description 任务描述
 * @property-read bool $even_in_maintenance_mode 维护模式下也执行
 * @property-read bool $without_overlapping 禁止重叠执行
 * @property-read bool $on_one_server 仅在单台服务器上执行
 * @property-read bool $run_in_background 后台运行
 * @property-read int $status 状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'command' => ['required', 'string', 'max:255'],
            'command_custom' => ['nullable', 'string', 'max:500'],
            'params' => ['nullable', 'array'],
            'params.*.name' => ['required_with:params', 'string', 'max:100'],
            'params.*.value' => ['required_with:params', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required_with:options', 'string', 'max:100'],
            'options_with_value' => ['nullable', 'array'],
            'options_with_value.*.name' => ['required_with:options_with_value', 'string', 'max:100'],
            'options_with_value.*.value' => ['required_with:options_with_value', 'string', 'max:255'],
            'expression' => ['required', 'string', 'max:64'],
            'environments' => ['nullable', 'array'],
            'environments.*' => ['string', 'in:local,development,testing,production'],
            'description' => ['nullable', 'string', 'max:255'],
            'even_in_maintenance_mode' => ['boolean'],
            'without_overlapping' => ['boolean'],
            'on_one_server' => ['boolean'],
            'run_in_background' => ['boolean'],
            'status' => ['required', 'integer', Rule::enum(ScheduleTaskStatus::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'even_in_maintenance_mode' => (bool) $this->input('even_in_maintenance_mode', false),
            'without_overlapping' => (bool) $this->input('without_overlapping', false),
            'on_one_server' => (bool) $this->input('on_one_server', false),
            'run_in_background' => (bool) $this->input('run_in_background', false),
        ]);
    }
}
