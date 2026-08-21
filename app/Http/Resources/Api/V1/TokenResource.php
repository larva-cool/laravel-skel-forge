<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\System\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 已经签发的 Token
 *
 * @mixin PersonalAccessToken
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class TokenResource extends JsonResource
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
            'name' => $this->name,
            'created_at' => $this->created_at->toDateTimeString(),
            'last_used_at' => $this->last_used_at?->toDateTimeString(),
            'expires_at' => $this->expires_at?->toDateTimeString(),
        ];
    }
}
