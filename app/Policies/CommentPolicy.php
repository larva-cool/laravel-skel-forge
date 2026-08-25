<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Policies;

use App\Models\Content\Comment;
use App\Models\User;

/**
 * 评论策略
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CommentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(User $user, Comment $comment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
