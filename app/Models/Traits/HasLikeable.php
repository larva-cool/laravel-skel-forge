<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Content\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 点赞
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasLikeable
{
    /**
     * 点赞列表
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'source');
    }

    /**
     * 是否被指定用户点赞
     */
    public function isLikedBy(User|int|string $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return $this->likes()->where('user_id', $userId)->exists();
    }
}
