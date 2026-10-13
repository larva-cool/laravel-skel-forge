<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace App\Policies;

use App\Models\Content\Follow;
use App\Models\User;

class FollowPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Follow $follow): bool
    {
        return $user->id === $follow->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Follow $follow): bool
    {
        return $user->id === $follow->user_id;
    }
}
