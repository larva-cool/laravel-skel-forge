<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\UserSegment\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * 分群详情资源（含 users_count 与 last_synced_*）
 *
 * @mixin Segment
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usersCount = $this->whenCounted('memberships');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'label_type' => $this->label_type ?: 'default',
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'group' => $this->group,
            'description' => $this->description,
            'sort' => $this->sort,
            'status' => (bool) $this->status,
            'rule' => $this->rule,
            'last_synced_at' => $this->last_synced_at instanceof Carbon
                ? $this->last_synced_at->toDateTimeString()
                : null,
            'last_synced_user_count' => $this->last_synced_user_count,
            'users_count' => $usersCount,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
