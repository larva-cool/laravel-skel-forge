<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 定时任务管理资源
 *
 * @mixin Schedule
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleResource extends JsonResource
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
            'command' => $this->command,
            'command_custom' => $this->command_custom,
            'params' => $this->params ?? [],
            'options' => $this->options ?? [],
            'options_with_value' => $this->options_with_value ?? [],
            'expression' => $this->expression,
            'environments' => $this->environments,
            'description' => $this->description,
            'even_in_maintenance_mode' => $this->even_in_maintenance_mode,
            'without_overlapping' => $this->without_overlapping,
            'on_one_server' => $this->on_one_server,
            'run_in_background' => $this->run_in_background,
            'status' => $this->status->value,
            'status_text' => $this->status->label(),
            'command_string' => $this->getCommandString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
