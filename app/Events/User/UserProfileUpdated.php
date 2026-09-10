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
 * 用户资料已更新事件
 *
 * 在用户修改昵称、头像、性别、生日、签名、地区等资料后触发，
 * 用于驱动"完善资料"类任务的进度判定。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserProfileUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}
}
