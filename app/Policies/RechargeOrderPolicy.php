<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet\RechargeOrder;

/**
 * 充值订单策略
 *
 * 前台用户仅可查看自己名下的充值订单。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeOrderPolicy
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
    public function view(User $user, RechargeOrder $order): bool
    {
        return $order->owner_type === $user->getMorphClass() && $order->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
