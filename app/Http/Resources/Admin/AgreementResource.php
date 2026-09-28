<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Operation\Agreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 协议后台管理资源
 *
 * @mixin Agreement
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementResource extends JsonResource
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
            'title' => $this->title,
            'category' => $this->category,
            'content' => $this->when(
                $request->routeIs('admin.agreements.show'),
                $this->content
            ),
            'version' => $this->version,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status?->labelType(),
            'effective_at' => $this->effective_at?->toDateTimeString(),
            'sort' => $this->sort,
            'admin_id' => $this->admin_id,
            'admin' => $this->whenLoaded('admin', fn () => [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
