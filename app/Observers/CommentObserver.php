<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Observers;

use App\Models\Content\Comment;

class CommentObserver
{
    /**
     * Handle the Comment "deleted" event.
     *
     * 递归删除所有回复评论。
     */
    public function deleted(Comment $comment): void
    {
        $comment->comments()->each(function (Comment $reply) {
            $reply->delete();
        });
    }
}
