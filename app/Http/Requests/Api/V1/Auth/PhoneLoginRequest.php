<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Rules\PhoneRule;
use App\Rules\SmsCaptchaRule;
use App\Support\UserHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * 短信验证码登录
 *
 * @property string $phone 手机号码
 * @property string $verify_code 短信验证码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PhoneLoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', new PhoneRule],
            'verify_code' => ['required', 'digits_between:4,6', new SmsCaptchaRule('phone', $this->ip())],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $phone = $this->string('phone')->toString();
        $user = User::query()->where('phone', $phone)->first();
        if (! $user) {
            if (! settings('user.enable_phone_login_register', true)) {
                validation_exception('phone', trans('auth.account_does_not_exist'));
            }
            $user = UserHelper::findOrCreatePhone($phone);
        }
        if (! $user) {
            validation_exception('phone', trans('auth.account_does_not_exist'));
        }
        if ($user->status->isFrozen()) {// 禁止掉的用户不允许登录
            $user->tokens()->delete(); // 剔除所有客户端
            validation_exception('account', trans('user.blocked'));
        }

        return $user;
    }
}
