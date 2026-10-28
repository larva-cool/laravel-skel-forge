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
 * 自动化节日保存请求（创建/编辑）
 *
 * @property-read string $name 节日名称
 * @property-read FestivalDateType $date_type 日期类型
 * @property-read int $month 月份
 * @property-read int $day 日
 * @property-read bool $status 启用状态
 * @property-read int $sort 排序权重
 * @property-read string|null $remark 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationFestivalSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxDay = $this->input('date_type') === FestivalDateType::LUNAR->value ? 30 : 31;

        return [
            'name' => ['required', 'string', 'max:64'],
            'date_type' => ['required', 'string', new Enum(FestivalDateType::class)],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'day' => ['required', 'integer', 'min:1', "max:{$maxDay}"],
            'status' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
