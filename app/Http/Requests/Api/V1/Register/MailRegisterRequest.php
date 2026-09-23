<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Register;

use App\Models\Invite\InviteCode;
use App\Models\User;
use App\Rules\UsernameRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 邮箱注册
 *
 * @property-read string $username 用户名
 * @property-read string $email 邮箱
 * @property-read string $password 密码
 * @property-read string|null $invite_code 邀请码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class MailRegisterRequest extends FormRequest
{
    /**
     * 仅在填写了 username 时将其纳入校验数据，保持 optional 语义。
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('username')) {
            $this->merge(['username' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:255', new UsernameRule, Rule::unique(User::class, 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', Password::default()],
            'invite_code' => ['nullable', 'string', Rule::exists(InviteCode::class, 'code')],
        ];
    }
}
