<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Content\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 评论资源
 *
 * @mixin Comment
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'is_top' => $this->is_top,
            'status' => $this->status,
            'content' => $this->content,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'mentioned_users' => $this->mentioned_users ?? [],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
