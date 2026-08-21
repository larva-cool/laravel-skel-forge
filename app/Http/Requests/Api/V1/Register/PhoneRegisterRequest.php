<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Register;

use App\Models\User;
use App\Models\User\UserExtra;
use App\Rules\PhoneRule;
use App\Rules\SmsCaptchaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 短信验证码注册
 *
 * @property-read string $phone 手机号码
 * @property-read string $verify_code 验证码
 * @property-read string $password 密码
 * @property-read string|null $invite_code 邀请码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class PhoneRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', new PhoneRule, Rule::unique(User::class, 'phone')],
            'verify_code' => ['required', 'min:4', 'max:6', new SmsCaptchaRule('phone', $this->ip())],
            'password' => ['nullable', Password::default()],
            'invite_code' => ['nullable', 'string', Rule::exists(UserExtra::class, 'invite_code')],
        ];
    }
}
