<?php

namespace App\Observers;

use App\Models\Content\Like;

class LikeObserver
{
    /**
     * Handle the Like "created" event.
     */
    public function created(Like $like): void
    {
        $like->source()->increment('like_count');
    }

    /**
     * Handle the Like "deleted" event.
     */
    public function deleted(Like $like): void
    {
        $like->source()->decrement('like_count');
    }
}
