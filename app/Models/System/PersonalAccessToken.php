<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Models\Traits\DateTimeFormatter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * 个人访问令牌
 *
 * @property int $id ID
 * @property int $tokenable_id ID
 * @property string $tokenable_type 类型
 * @property string $name 名称
 * @property string $token 令牌
 * @property string $abilities 权限
 * @property Carbon $last_used_at 最后使用时间
 * @property Carbon|null $expires_at 过期时间
 * @property Carbon $created_at 添加时间
 * @property Carbon $updated_at 更新时间
 * @property Model $tokenable 令牌所属模型
 *
 * @codeCoverageIgnore
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use DateTimeFormatter;

    /**
     * 模型启动时注册创建事件：用户类型 token 自动按系统设置设置过期时间
     */
    protected static function booted(): void
    {
        static::creating(function (self $token) {
            // 已显式指定过期时间的，不再覆盖
            if ($token->expires_at !== null) {
                return;
            }

            // 仅普通用户 token 受「用户token有效期」设置控制
            if ($token->tokenable_type && is_a($token->tokenable_type, User::class, true)) {
                $minutes = (int) settings('user.token_expiration', config('sanctum.expiration'));
                if ($minutes > 0) {
                    $token->expires_at = Carbon::now()->addMinutes($minutes);
                }
            }
        });
    }
}
