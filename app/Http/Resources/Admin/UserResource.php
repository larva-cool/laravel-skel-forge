<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户资源
 *
 * @mixin User
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserResource extends JsonResource
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
            'available_points' => $this->available_points,
            'available_coins' => $this->available_coins,
            'login_count' => $this->login_count,
            'last_login_ip' => $this->last_login_ip,
            'vip_expires_at' => $this->vip_expires_at?->toDateTimeString(),
            'is_vip' => $this->isVip(),
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'last_active_at' => $this->last_active_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
