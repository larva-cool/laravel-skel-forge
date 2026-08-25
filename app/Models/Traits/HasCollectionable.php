<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Content\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 收藏
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasCollectionable
{
    /**
     * Collection Relation
     */
    public function collections(): MorphMany
    {
        return $this->morphMany(Collection::class, 'source');
    }

    /**
     * 是否被指定用户收藏目标
     */
    public function isCollectedBy(User|string|int $user): bool
    {
        if ($user instanceof User) {
            return $this->collections()->where('user_id', $user->id)->exists();
        }

        return $this->collections()->where('user_id', (int) $user)->exists();
    }
}
