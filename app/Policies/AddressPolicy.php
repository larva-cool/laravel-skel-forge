<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\User\Address;

/**
 * 收货地址策略
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AddressPolicy
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
    public function view(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }
}
