<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\NameRule;
use App\Rules\PhoneRule;
use App\Rules\UsernameRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 用户更新请求
 *
 * @property-read string|null $username 用户名
 * @property-read string|null $email 邮箱
 * @property-read string|null $phone 手机号
 * @property-read string|null $name 昵称
 * @property-read int $status 状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserUpdateRequest extends FormRequest
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
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->route('user')?->id ?? (int) $this->route('user');

        return [
            'username' => [
                'nullable', 'string', 'min:3', 'max:50', new UsernameRule,
                Rule::unique(User::class, 'username')->ignore($userId),
            ],
            'email' => [
                'nullable', 'email', 'max:100',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'phone' => [
                'nullable', 'string', new PhoneRule,
                Rule::unique(User::class, 'phone')->ignore($userId),
            ],
            'name' => ['nullable', 'string', 'min:2', 'max:50', new NameRule],
            'status' => ['required', 'integer', Rule::enum(UserStatus::class)],
        ];
    }
}
