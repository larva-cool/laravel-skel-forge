<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Observers;

use App\Models\Admin\Admin;

class AdminObserver
{
    /**
     * Handle the Admin "updated" event.
     */
    public function updated(Admin $admin): void
    {
        if ($admin->wasChanged('status') && $admin->status->isDisabled()) {
            $admin->tokens()->delete();
        }
    }
}
