<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Automation;

use App\Enums\AutomationTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * 自动化规则列表筛选请求
 *
 * @property-read string|null $keyword 关键词搜索（规则名称）
 * @property-read string|null $trigger_type 触发类型筛选
 * @property-read bool|null $status 状态筛选
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationRuleIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'trigger_type' => ['sometimes', 'string', new Enum(AutomationTriggerType::class)],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
