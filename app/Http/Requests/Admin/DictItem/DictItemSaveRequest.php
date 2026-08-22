<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\DictItem;

use App\Enums\DictLabelType;
use App\Enums\DictStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 字典数据保存请求（创建/编辑）
 *
 * @property-read int $dict_id 字典ID
 * @property-read string $label 字典项标签
 * @property-read string $value 字典项值
 * @property-read string $label_type 标签样式
 * @property-read int $status 状态
 * @property-read int $sort 排序权重
 * @property-read string|null $remark 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DictItemSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dictItemId = (int) $this->route('dict_item');

        return [
            'dict_id' => ['required', 'integer', Rule::exists('dicts', 'id')],
            'label' => ['required', 'string', 'max:100'],
            'value' => [
                'required', 'string', 'max:100',
                Rule::unique('dict_items', 'value')
                    ->where('dict_id', $this->input('dict_id'))
                    ->ignore($dictItemId),
            ],
            'label_type' => ['sometimes', 'string', Rule::enum(DictLabelType::class)],
            'status' => ['required', 'integer', Rule::enum(DictStatus::class)],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
