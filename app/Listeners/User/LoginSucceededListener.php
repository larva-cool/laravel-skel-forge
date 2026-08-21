<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Listeners\User;

use App\Events\User\LoginSucceeded;
use Illuminate\Support\Carbon;

/**
 * 登录成功时间监听器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginSucceededListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LoginSucceeded $event): void
    {
        // 记录登录历史（LoginHistory 模型创建时会自动更新用户登录信息）
        $event->user->loginHistories()->create([
            'ip' => $event->ip,
            'port' => $event->port,
            'user_agent' => $event->userAgent,
            'login_at' => Carbon::now(),
        ]);
    }
}
