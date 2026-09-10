<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
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
     * 通过手机创建用户
     *
     * @param  string  $name  昵称
     * @param  int|string  $phone  手机号
     * @param  string|null  $password  密码
     */
    public static function createByPhone(string $name, int|string $phone, ?string $password = null): User
    {
        return User::create([
            'name' => $name,
            'username' => self::generateUsername($phone),
            'phone' => $phone,
            'password' => $password,
        ]);
    }

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

    /**
     * 延长用户VIP有效期
     *
     * @param  int|string|User  $user  用户ID或用户模型
     * @param  int  $days  延长天数，必须为正整数
     * @return User 返回更新后的用户模型
     */
    public static function extendVip(int|string|User $user, int $days): User
    {
        if ($days <= 0) {
            throw new \InvalidArgumentException('VIP延长天数必须为正整数');
        }

        if (! $user instanceof User) {
            $user = User::findOrFail((int) $user);
        }

        $now = Carbon::now();
        $currentExpiry = $user->vip_expires_at;

        // 如果已过期，从当前时间开始算；否则从到期日往后延
        $newExpiry = ($currentExpiry && $currentExpiry->gt($now))
            ? $currentExpiry->copy()->addDays($days)
            : $now->copy()->addDays($days);

        $user->update(['vip_expires_at' => $newExpiry]);

        return $user->refresh();
    }
}
