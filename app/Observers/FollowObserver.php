<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Observers;

use App\Models\Content\Follow;

class FollowObserver
{
    /**
     * Handle the Follow "created" event.
     */
    public function created(Follow $follow): void
    {
        $follow->source()->increment('follow_count');
    }

    /**
     * Handle the Follow "deleted" event.
     */
    public function deleted(Follow $follow): void
    {
        $follow->source()->decrement('follow_count');
    }
}
