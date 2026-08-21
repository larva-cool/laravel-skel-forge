<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\User;

use App\Models\System\LoginHistory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 当日首次登录
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class TodayFirstLogged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The login record.
     */
    public LoginHistory $loginHistory;

    /**
     * Create a new event instance.
     */
    public function __construct(LoginHistory $loginHistory)
    {
        $this->loginHistory = $loginHistory;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $userType = strtoupper($this->loginHistory->user_type);

        return [
            new PrivateChannel($userType.'.'.$this->loginHistory->user_id),
        ];
    }
}
