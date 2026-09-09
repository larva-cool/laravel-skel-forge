<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Invite\InviteRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 邀请记录资源（后台）
 *
 * @mixin InviteRecord
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRecordResource extends JsonResource
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
            'inviter_type' => $this->inviter_type,
            'inviter_id' => $this->inviter_id,
            'inviter_name' => $this->whenLoaded('inviter', fn () => $this->inviter?->name ?? $this->inviter?->username),
            'invitee_type' => $this->invitee_type,
            'invitee_id' => $this->invitee_id,
            'invitee_name' => $this->whenLoaded('invitee', fn () => $this->invitee?->name ?? $this->invitee?->username),
            'description' => $this->description,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
