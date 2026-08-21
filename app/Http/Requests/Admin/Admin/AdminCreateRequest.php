<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Rules\NameRule;
use App\Rules\PhoneRule;
use App\Rules\UsernameRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * 管理员创建请求
 *
 * @property-read string $username 用户名
 * @property-read string|null $email 邮箱
 * @property-read string|null $phone 手机号
 * @property-read string $name 昵称
 * @property-read string $password 密码
 * @property-read int $status 状态
 * @property-read string[]|null $roles 角色名称数组
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AdminCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', new UsernameRule, Rule::unique(Admin::class, 'username')],
            'email' => ['nullable', 'email', 'max:100', Rule::unique(Admin::class, 'email')],
            'phone' => ['nullable', 'string', new PhoneRule, Rule::unique(Admin::class, 'phone')],
            'name' => ['required', 'string', 'min:2', 'max:50', new NameRule],
            'password' => ['required', 'string', Password::defaults()],
            'status' => ['required', 'integer', Rule::enum(AdminStatus::class)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:50', Rule::exists(Role::class, 'name')->where('guard_name', AdminMenu::GUARD_NAME)],
        ];
    }
}
