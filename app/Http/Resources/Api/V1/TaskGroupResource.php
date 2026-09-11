<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 任务分组资源
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class TaskGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'reset_type' => $this->reset_type->value,
            'reset_type_label' => $this->reset_type->label(),
            'sort_order' => $this->sort_order,
            'icon' => $this->icon,
            'description' => $this->description,
            'tasks' => TaskResource::collection($this->activeTasks),
        ];
    }
}
