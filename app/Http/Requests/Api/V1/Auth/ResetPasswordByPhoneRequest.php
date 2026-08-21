<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Rules\PhoneRule;
use App\Rules\SmsCaptchaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 短信验证码重设密码
 *
 * @property string $phone 手机号
 * @property string $verify_code 验证码
 * @property string $password 密码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ResetPasswordByPhoneRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', new PhoneRule, Rule::exists(User::class)],
            'verify_code' => ['required', 'min:4', 'max:6', new SmsCaptchaRule('phone', $this->getClientIp())],
            'password' => ['required', 'string', Password::min(6)->max(20)->uncompromised()],
        ];
    }
}
