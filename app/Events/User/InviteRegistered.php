<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\User;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 邀请注册事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class InviteRegistered
{
    use Dispatchable, SerializesModels;

    public User $user;

    /**
     * 邀请码
     */
    public string $inviteCode;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $inviteCode)
    {
        $this->user = $user;
        $this->inviteCode = $inviteCode;
    }
}
