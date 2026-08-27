<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\Admin;

use App\Models\Admin\Admin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 登录失败
 *
 * 仅在能够识别到具体管理员账号时派发（密码错误、账号被禁用等），
 * 用于将失败尝试记录到登录历史，便于安全审计。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginFailed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Admin  $user  尝试登录的管理员
     * @param  string  $ip  来源 IP
     * @param  string  $port  来源端口
     * @param  string|null  $userAgent  浏览器 UA
     */
    public function __construct(
        public Admin $user,
        public string $ip,
        public string $port,
        public ?string $userAgent = null,
    ) {}
}
