<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

/**
 * 角色保存请求（创建/编辑）
 *
 * @property-read string $name 角色名称（同时作为编码）
 * @property-read int[]|null $permissions 权限ID数组
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RoleSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $roleId = (int) $this->route('role')?->id ?? (int) $this->route('role');

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:50',
                Rule::unique('roles', 'name')->ignore($roleId)->where('guard_name', AdminMenu::GUARD_NAME),
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:50'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists(Permission::class, 'id')],
        ];
    }
}
