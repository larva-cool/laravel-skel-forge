<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Automation;

use App\Enums\AutomationLogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * 自动化触发日志列表筛选请求
 *
 * @property-read int|null $rule_id 规则 ID 筛选
 * @property-read int|null $user_id 用户 ID 筛选
 * @property-read string|null $status 状态筛选
 * @property-read string|null $date_from 事件日期起
 * @property-read string|null $date_to 事件日期止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationLogIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rule_id' => ['sometimes', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', new Enum(AutomationLogStatus::class)],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
