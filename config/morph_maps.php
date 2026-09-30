<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

return [
    'user' => \App\Models\User::class,
    'admin' => \App\Models\Admin\Admin::class,
    'point' => \App\Models\Point\PointRecord::class,
    'comment' => \App\Models\Content\Comment::class,
    'wallet_trade' => \App\Models\Wallet\WalletTrade::class,
    'recharge' => \App\Models\Wallet\Recharge::class,
    'withdraw' => \App\Models\Wallet\Withdraw::class,
    'invite_record' => \App\Models\Invite\InviteRecord::class,
    'invite_code' => \App\Models\Invite\InviteCode::class,
    'task' => \App\Models\Task\Task::class,
    'open_account' => \App\Models\Open\OpenAccount::class,
    'report' => \App\Models\Content\Report::class,
];
