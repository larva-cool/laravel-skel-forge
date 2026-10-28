<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Automation\AutomationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 自动化规则后台管理资源
 *
 * @mixin AutomationRule
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationRuleResource extends JsonResource
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
            'trigger_type' => $this->trigger_type?->value,
            'trigger_type_label' => $this->trigger_type?->label(),
            'trigger_type_label_type' => $this->trigger_type?->labelType(),
            'trigger_config' => $this->trigger_config,
            'segment_id' => $this->segment_id,
            'segment' => $this->whenLoaded('segment', fn () => [
                'id' => $this->segment->id,
                'name' => $this->segment->name,
            ]),
            'title' => $this->title,
            'content' => $this->content,
            'channels' => $this->channels,
            'reward_type' => $this->reward_type?->value,
            'reward_type_label' => $this->reward_type?->label(),
            'reward_amount' => $this->reward_amount,
            'status' => $this->status,
            'last_triggered_at' => $this->last_triggered_at?->toDateTimeString(),
            'last_triggered_count' => $this->last_triggered_count,
            'admin' => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
