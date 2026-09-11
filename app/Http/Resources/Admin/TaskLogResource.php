<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 后台任务日志资源
 *
 * @mixin \App\Models\Task\TaskLog
 */
class TaskLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'nickname' => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'name' => $this->task->name,
                'code' => $this->task->code,
            ]),
            'period_key' => $this->period_key,
            'reward_type' => $this->reward_type->value,
            'reward_type_label' => $this->reward_type->label(),
            'reward_amount' => $this->reward_amount,
            'source_type' => $this->source_type,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
