<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Open\OpenAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 开放平台账户资源
 *
 * @mixin OpenAccount
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class OpenAccountResource extends JsonResource
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
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'rate_limit' => $this->rate_limit,
            'available_points' => $this->available_points,
            'available_coins' => $this->available_coins,
            'balance' => $this->balance,
            'frozen_balance' => $this->frozen_balance,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'identity_type' => $this->identity_type,
            'identity_type_label' => $this->identity_type?->label(),
            'identity_status' => $this->identity_status,
            'identity_status_label' => $this->identity_status?->label(),
            'identity_verified' => $this->identity_verified,
            'login_count' => $this->login_count,
            'last_login_ip' => $this->last_login_ip,
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'last_active_at' => $this->last_active_at?->toDateTimeString(),
            'last_request_at' => $this->last_request_at?->toDateTimeString(),
            'last_request_ip' => $this->last_request_ip,
            'tokens_count' => $this->when(array_key_exists('tokens_count', $this->getAttributes()), fn () => (int) $this->tokens_count),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
