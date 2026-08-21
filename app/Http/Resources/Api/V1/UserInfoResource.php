<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户信息资源
 *
 * @mixin User
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class UserInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->id,
            'user_name' => $this->username,
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'avatar' => $this->avatar,
        ];
    }
}
