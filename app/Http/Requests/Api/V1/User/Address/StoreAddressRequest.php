<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User\Address;

use App\Rules\PhoneRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 添加收货地址
 *
 * @property-read string $name 姓名
 * @property-read string $country 国家
 * @property-read int $phone 手机
 * @property-read bool $is_default 是否默认
 * @property-read string $province 省
 * @property-read string $city 城市
 * @property-read string $district 区县
 * @property-read string $address 地址
 * @property-read int $zipcode 邮编
 */
class StoreAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 准备验证数据
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
            'country' => $this->post('country') ?? 'CN',
            'is_default' => $this->post('is_default') ?? 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['numeric'],
            'name' => ['required', 'string', 'min:1'],
            'country' => ['required', 'string'],
            'phone' => ['required', new PhoneRule],
            'is_default' => ['required', 'boolean'],
            'province' => ['required', 'string'],
            'city' => ['required', 'string'],
            'district' => ['required', 'string'],
            'address' => ['required', 'string'],
            'zipcode' => ['nullable', 'numeric'],
        ];
    }
}
