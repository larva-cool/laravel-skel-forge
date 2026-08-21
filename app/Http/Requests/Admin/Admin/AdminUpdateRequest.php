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
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * 管理员更新请求
 *
 * @property-read string|null $email 邮箱
 * @property-read string|null $phone 手机号
 * @property-read string $name 昵称
 * @property-read string|null $password 密码（留空则不修改）
 * @property-read int $status 状态
 * @property-read string[]|null $roles 角色名称数组
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AdminUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adminId = (int) $this->route('admin')?->id ?? (int) $this->route('admin');

        return [
            'email' => [
                'nullable', 'email', 'max:100',
                Rule::unique(Admin::class, 'email')->ignore($adminId),
            ],
            'phone' => [
                'nullable', 'string', new PhoneRule,
                Rule::unique(Admin::class, 'phone')->ignore($adminId),
            ],
            'name' => ['required', 'string', 'min:2', 'max:50', new NameRule],
            'password' => ['nullable', 'string', Password::defaults()],
            'status' => ['required', 'integer', Rule::enum(AdminStatus::class)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:50', Rule::exists(Role::class, 'name')->where('guard_name', AdminMenu::GUARD_NAME)],
        ];
    }
}
