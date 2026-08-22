<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Dict;

use App\Enums\DictStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 字典保存请求（创建/编辑）
 *
 * @property-read string $name 字典名称
 * @property-read string $code 字典编码
 * @property-read int $status 状态
 * @property-read int $sort 排序权重
 * @property-read string|null $remark 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DictSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dictId = (int) $this->route('dict');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('dicts', 'code')->ignore($dictId),
            ],
            'status' => ['required', 'integer', Rule::enum(DictStatus::class)],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
