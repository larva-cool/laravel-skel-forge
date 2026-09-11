<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Task\TaskGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskGroup
 */
class TaskGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'reset_type' => $this->reset_type?->value,
            'reset_type_label' => $this->reset_type?->label(),
            'sort_order' => $this->sort_order,
            'icon' => $this->icon,
            'description' => $this->description,
            'status' => $this->status,
            'tasks_count' => $this->when(isset($this->tasks_count), fn () => $this->tasks_count),
            'tasks' => $this->when(
                $request->routeIs('admin.task-groups.show'),
                fn () => TaskResource::collection($this->whenLoaded('tasks'))
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
