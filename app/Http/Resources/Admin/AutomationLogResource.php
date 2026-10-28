<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Automation\AutomationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 自动化触发日志后台管理资源
 *
 * @mixin AutomationLog
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationLogResource extends JsonResource
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
            'rule_id' => $this->rule_id,
            'rule' => $this->whenLoaded('rule', fn () => [
                'id' => $this->rule->id,
                'name' => $this->rule->name,
                'trigger_type' => $this->rule->trigger_type?->value,
                'trigger_type_label' => $this->rule->trigger_type?->label(),
            ]),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'avatar' => $this->user->avatar,
            ] : null),
            'trigger_date' => $this->trigger_date?->toDateString(),
            'festival' => $this->whenLoaded('festival', fn () => $this->festival ? [
                'id' => $this->festival->id,
                'name' => $this->festival->name,
            ] : null),
            'channels' => $this->channels,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status?->labelType(),
            'error' => $this->error,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
