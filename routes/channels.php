<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

// 用户私有频道
Broadcast::channel('User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
// 管理员私有频道
Broadcast::channel('Admin.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
