<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\Invite;

use App\Models\Invite\InviteRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 邀请关系已建立事件
 *
 * 当一条邀请关系成功写入 invite_records 表后触发。
 * 监听方可在此事件基础上处理奖励发放、任务进度推进等后续逻辑。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRelationshipCreated
{
    use Dispatchable, SerializesModels;

    public InviteRecord $inviteRecord;

    /**
     * Create a new event instance.
     */
    public function __construct(InviteRecord $inviteRecord)
    {
        $this->inviteRecord = $inviteRecord;
    }
}
