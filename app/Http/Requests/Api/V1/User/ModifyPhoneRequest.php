<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Models\User;
use App\Rules\PhoneRule;
use App\Rules\SmsCaptchaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 修改手机号码请求
 *
 * @property-read int $phone 手机号
 * @property-read string $verify_code 验证码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ModifyPhoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', new PhoneRule, Rule::unique(User::class)->ignore($this->user()->id)],
            'verify_code' => ['required', 'min:4', 'max:6', new SmsCaptchaRule('phone', $this->ip())],
        ];
    }
}
