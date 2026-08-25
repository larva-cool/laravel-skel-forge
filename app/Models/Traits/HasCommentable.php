<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Content\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 评论
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasCommentable
{
    /**
     * Collection Relation
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'source');
    }
}
