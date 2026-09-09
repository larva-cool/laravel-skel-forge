<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Listeners\User;

use App\Events\User\InviteRegistered;
use App\Services\InviteService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 邀请注册监听器
 *
 * 监听 InviteRegistered 事件，异步建立邀请关系。
 * 通过队列异步执行，解耦注册主流程，避免邀请逻辑影响注册响应速度。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRegisteredListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly InviteService $inviteService,
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InviteRegistered $event): void
    {
        $this->inviteService->createRelationshipByCode(
            $event->user,
            $event->inviteCode,
            '通过邀请码注册'
        );
    }
}
