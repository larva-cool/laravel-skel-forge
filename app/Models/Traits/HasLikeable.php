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
 * @property Model $this
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
trait HasLikeable
{
    /**
     * support Relation
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'source');
    }

    /**
     * 是否赞过
     */
    public function liked(User|int|string $user): bool
    {
        if ($user instanceof User) {
            return $this->likes()->where('user_id', $user->id)->exists();
        }

        return $this->likes()->where('user_id', (int) $user)->exists();
    }
}
