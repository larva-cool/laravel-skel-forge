<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User\Address;

use App\Rules\PhoneRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 更新收货地址
 *
 * @property-read string $name 姓名
 * @property-read string $country 国家
 * @property-read string $phone 手机
 * @property-read bool $is_default 是否默认
 * @property-read string $province 省
 * @property-read string $city 城市
 * @property-read string $district 区县
 * @property-read string $address 地址
 * @property-read int $zipcode 邮编
 */
class UpdateAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:1'],
            'country' => ['sometimes', 'string'],
            'phone' => ['sometimes', new PhoneRule],
            'is_default' => ['sometimes', 'boolean'],
            'province' => ['sometimes', 'string'],
            'city' => ['sometimes', 'string'],
            'district' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
            'zipcode' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}
