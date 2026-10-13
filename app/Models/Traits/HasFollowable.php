<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Content\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 关注
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasFollowable
{
    /**
     * 关注列表
     */
    public function follows(): MorphMany
    {
        return $this->morphMany(Follow::class, 'source');
    }

    /**
     * 是否被指定用户关注
     */
    public function isFollowedBy(User|int|string $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return $this->follows()->where('user_id', $userId)->exists();
    }
}
