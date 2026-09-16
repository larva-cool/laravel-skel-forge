<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\User;

use App\Models\User\IdentityVerification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 实名认证驳回事件
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityRejected implements ShouldBroadcast, ShouldHandleEventsAfterCommit, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithSockets, SerializesModels;

    public Model $verifiable;

    public IdentityVerification $verification;

    public string $reason;

    public ?string $remark;

    /**
     * Create a new event instance.
     */
    public function __construct(Model $verifiable, IdentityVerification $verification, string $reason, ?string $remark = null)
    {
        $this->verifiable = $verifiable;
        $this->verification = $verification;
        $this->reason = $reason;
        $this->remark = $remark;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(class_basename($this->verifiable).'.'.$this->verifiable->id),
        ];
    }
}
