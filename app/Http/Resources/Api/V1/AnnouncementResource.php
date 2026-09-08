<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Operation\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公告前台公开资源
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
                $request->routeIs('api.v1.announcements.show'),
                $this->content
            ),
            'cover_image' => $this->cover_image,
            'published_at' => $this->published_at?->toDateTimeString(),
        ];
    }
}
