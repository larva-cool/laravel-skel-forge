<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Invite\InviteRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 邀请记录资源（前台）
 *
 * @mixin InviteRecord
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invitee' => [
                'id' => $this->invitee_id,
                'name' => $this->invitee?->name,
                'username' => $this->invitee?->username,
                'avatar' => $this->invitee?->avatar,
            ],
            'description' => $this->description,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
