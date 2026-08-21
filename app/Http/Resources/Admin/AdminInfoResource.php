<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理员信息资源
 *
 * @mixin Admin
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AdminInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Admin $admin */
        $admin = $this->resource;

        // 获取所有权限标识（包括通过角色继承的）
        $allPermissions = $admin->getAllPermissions()->pluck('name')->toArray();

        // 获取角色名称列表
        $roles = $admin->getRoleNames()->toArray();

        return [
            'user_id' => $admin->id,
            'user_name' => $admin->username,
            'email' => $admin->email ?? '',
            'avatar' => $admin->avatar ?? '', // 管理员头像
            'roles' => $roles,
            'buttons' => $allPermissions,
        ];
    }
}
