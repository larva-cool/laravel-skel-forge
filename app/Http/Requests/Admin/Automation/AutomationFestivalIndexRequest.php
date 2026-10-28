<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Automation;

use App\Enums\FestivalDateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * 自动化节日列表筛选请求
 *
 * @property-read string|null $keyword 关键词搜索（节日名称）
 * @property-read string|null $date_type 日期类型筛选
 * @property-read bool|null $status 状态筛选
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationFestivalIndexRequest extends FormRequest
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
            'date_type' => ['sometimes', 'string', new Enum(FestivalDateType::class)],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
