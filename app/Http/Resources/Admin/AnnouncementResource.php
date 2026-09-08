<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Operation\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公告后台管理资源
 *
 * @mixin Announcement
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AnnouncementResource extends JsonResource
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
            'summary' => $this->summary,
            'content' => $this->when(
                $request->routeIs('admin.announcements.show'),
                $this->content
            ),
            'cover_image' => $this->cover_image,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status?->labelType(),
            'sort' => $this->sort,
            'admin_id' => $this->admin_id,
            'admin' => $this->whenLoaded('admin', fn () => [
                'user_id' => $this->admin->user_id,
                'name' => $this->admin->name,
            ]),
            'published_at' => $this->published_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
