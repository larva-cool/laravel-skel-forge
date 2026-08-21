<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\ScheduleLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 调度日志资源
 *
 * @mixin ScheduleLog
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleLogResource extends JsonResource
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
            'type' => $this->type,
            'expression' => $this->expression,
            'status' => $this->status->value,
            'status_text' => $this->status->label(),
            'exit_code' => $this->exit_code,
            'runtime' => $this->runtime,
            'exception' => $this->exception,
            'hostname' => $this->hostname,
            'started_at' => $this->started_at?->toDateTimeString(),
            'finished_at' => $this->finished_at?->toDateTimeString(),
        ];
    }
}
