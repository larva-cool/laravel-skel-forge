<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\User;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 登录成功
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginSucceeded implements ShouldBroadcast
{
    use Dispatchable,InteractsWithQueue, InteractsWithSockets, SerializesModels;

    /**
     * The authenticated user.
     */
    public Authenticatable $user;

    /**
     * The user ip.
     */
    public string $ip;

    /**
     * The user ip port.
     */
    public string $port;

    /**
     * The user agent.
     */
    public string $userAgent;

    /**
     * Create a new event instance.
     */
    public function __construct(Authenticatable $user, string $ip, string $port, string $userAgent)
    {
        $this->user = $user;
        $this->ip = $ip;
        $this->port = $port;
        $this->userAgent = $userAgent;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('User.'.$this->user->id),
        ];
    }
}
