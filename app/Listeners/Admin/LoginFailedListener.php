<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Listeners\Admin;

use App\Events\Admin\LoginFailed;
use Illuminate\Support\Carbon;

/**
 * 登录失败事件监听器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginFailedListener
{
    /**
     * Handle the event.
     */
    public function handle(LoginFailed $event): void
    {
        // 记录失败的登录历史（result 为 false 时不会更新用户的登录统计信息）
        $event->user->loginHistories()->create([
            'ip' => $event->ip,
            'port' => $event->port,
            'user_agent' => $event->userAgent,
            'login_at' => Carbon::now(),
            'result' => false,
        ]);
    }
}
