<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Observers;

use App\Models\Content\Collection;

class CollectionObserver
{
    /**
     * Handle the Collection "created" event.
     */
    public function created(Collection $collection): void
    {
        $collection->source()->increment('collection_count');
    }

    /**
     * Handle the Collection "deleted" event.
     */
    public function deleted(Collection $collection): void
    {
        $collection->source()->decrement('collection_count');
    }
}
