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
 * 管理员资源
 *
 * @mixin Admin
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'login_count' => $this->login_count,
            'last_login_ip' => $this->last_login_ip,
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'last_active_at' => $this->last_active_at?->toDateTimeString(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
