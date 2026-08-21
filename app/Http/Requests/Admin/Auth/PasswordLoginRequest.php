<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Auth;

use App\Models\Admin\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 登录验证
 *
 * @property-read string $account
 * @property-read string $password
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
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?Admin
    {
        $account = $this->string('account');
        $admin = Admin::findForAccount($account->toString());
        if (! $admin || ! Hash::check($this->string('password')->toString(), $admin->password)) {
            validation_exception('password', trans('auth.failed'));
        }
        if ($admin->status->isDisabled()) { // 禁止掉的用户不允许登录
            $admin->tokens()->delete();
            validation_exception('account', trans('admin.blocked'));
        }

        return $admin;
    }
}
