<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Support\UserHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 密码登录
 *
 * @property string $account 账户
 * @property string $password 密码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PasswordLoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'account' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:50'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $account = $this->string('account');
        $user = UserHelper::findForAccount($account->toString());
        if (! $user || ! Hash::check($this->string('password')->toString(), $user->password)) {
            validation_exception('password', trans('auth.failed'));
        }
        // 禁止掉的用户不允许登录
        if ($user->status->isFrozen()) {
            $user->tokens()->delete();
            validation_exception('account', trans('user.blocked'));
        }

        return $user;
    }
}
