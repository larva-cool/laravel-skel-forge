<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

/**
 * 用户助手类
 * 提供用户相关的静态方法，如通过账号查找用户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserHelper
{
    /**
     * 查找手机用户，如果没有则根据系统规则创建
     */
    public static function findOrCreatePhone(int|string $phone): ?User
    {
        /** @var User $user */
        $user = User::withTrashed()->where('phone', $phone)->first();
        if (! $user) {
            $user = User::create([
                'name' => self::generateUsername(mobile_replace($phone)),
                'phone' => $phone,
            ]);

            Event::dispatch(new Registered($user));
        } elseif ($user->trashed()) {
            return null;
        }

        return $user;
    }

    /**
     * 通过账号查找用户
     */
    public static function findForAccount(string $account): ?User
    {
        $account = trim($account);
        if ($account === '') {
            return null;
        }

        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', $account)->first();
        } elseif (preg_match('/^1[2-9]\d{9}$/', $account)) {
            return User::query()->where('phone', $account)->first();
        } else {
            return User::query()->where('username', $account)->first();
        }
    }

    /**
     * 生成可用的用户名
     *
     * @param  string  $username  基础用户名
     */
    public static function generateUsername(string $username): string
    {
        $original = $username;
        $suffix = 1;
        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $original.$suffix++;
        }

        return $username;
    }
}
