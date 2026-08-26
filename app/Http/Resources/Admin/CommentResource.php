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
            'is_reply' => $this->isReply(),
            'parent_comment' => $this->when(
                $this->isReply() && $this->relationLoaded('source'),
                fn () => $this->source instanceof Comment ? [
                    'id' => $this->source->id,
                    'user_id' => $this->source->user_id,
                    'username' => $this->source->user?->username,
                    'content' => $this->source->content,
                    'status' => $this->source->status?->value,
                    'status_label' => $this->source->status?->label(),
                    'status_label_type' => $this->source->status?->labelType() ?? 'default',
                    'created_at' => $this->source->created_at?->toDateTimeString(),
                ] : null
            ),
            'ip_address' => $this->when($request->routeIs('admin.comments.show'), $this->ip_address),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
