<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Content\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 评论资源（管理端）
 *
 * @mixin Comment
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isReply = $this->source_type === 'comment';
        $parent = $isReply && $this->relationLoaded('source') ? $this->source : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn () => $this->user?->username),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'is_top' => $this->is_top,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status?->labelType() ?? 'default',
            'content' => $this->content,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'mentioned_users' => $this->mentioned_users ?? [],
            'is_reply' => $isReply,
            'parent_comment' => $this->when(
                $parent instanceof Comment,
                fn () => [
                    'id' => $parent->id,
                    'user_id' => $parent->user_id,
                    'username' => $parent->user?->username,
                    'content' => $parent->content,
                    'status' => $parent->status?->value,
                    'status_label' => $parent->status?->label(),
                    'status_label_type' => $parent->status?->labelType() ?? 'default',
                    'created_at' => $parent->created_at?->toDateTimeString(),
                ]
            ),
            'ip_address' => $this->when($request->routeIs('admin.comments.show'), $this->ip_address),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
