<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Setting;

use App\Enums\SettingInputType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 配置保存请求（创建/编辑）
 *
 * @property-read string $name 配置名称
 * @property-read string $key 配置 Key
 * @property-read string $value 配置值
 * @property-read string $cast_type 配置变量类型
 * @property-read string $input_type 配置输入类型
 * @property-read string|null $param 配置参数
 * @property-read int $sort 排序权重
 * @property-read string|null $remark 配置描述
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SettingSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $settingId = (int) $this->route('setting');

        return [
            'name' => ['required', 'string', 'max:100'],
            'key' => [
                'required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_.-]+$/',
                Rule::unique('settings', 'key')->ignore($settingId),
            ],
            'value' => ['nullable', 'string'],
            'cast_type' => ['required', 'string', 'in:string,int,bool,float,json'],
            'input_type' => ['required', 'string', Rule::enum(SettingInputType::class)],
            'param' => ['nullable', 'string', 'json'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
