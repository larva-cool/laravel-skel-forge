<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * 用户策略
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserPolicy
{
    /**
     * 是否允许用户注册
     */
    public function register(?User $user): Response
    {
        return settings('user.enable_register', true) ? Response::allow()
            : Response::deny(__('user.register_disabled'));
    }

    /**
     * 是否允许用户通过手机号注册
     */
    public function phoneRegister(?User $user): Response
    {
        return settings('user.enable_phone_register', true) ? Response::allow()
            : Response::deny(__('user.phone_register_disabled'));
    }

    /**
     * 是否允许用户手机号登录
     */
    public function phoneLogin(?User $user): Response
    {
        return settings('user.enable_phone_login', true) ? Response::allow()
            : Response::deny(__('user.phone_login_disabled'));
    }

    /**
     * 是否允许用户密码登录
     */
    public function passwordLogin(?User $user): Response
    {
        return settings('user.enable_password_login', true) ? Response::allow()
            : Response::deny(__('user.password_login_disabled'));
    }
}
