<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group->id,
                'name' => $this->group->name,
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'description' => $this->description,
            'condition' => $this->condition,
            'reward_type' => $this->reward_type?->value,
            'reward_type_label' => $this->reward_type?->label(),
            'reward_amount' => $this->reward_amount,
            'claim_mode' => $this->claim_mode?->value,
            'claim_mode_label' => $this->claim_mode?->label(),
            'sort_order' => $this->sort_order,
            'target_value' => (int) ($this->condition['target_value'] ?? 0),
            'start_at' => $this->start_at?->toDateTimeString(),
            'end_at' => $this->end_at?->toDateTimeString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
