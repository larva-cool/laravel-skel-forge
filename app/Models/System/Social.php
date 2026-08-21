<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\SocialProvider;
use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Overtrue\Socialite\Contracts\UserInterface;

/**
 * 用户社交账号
 *
 * @property int $id ID
 * @property int|null $user_id 用户ID
 * @property string $user_type 用户类型
 * @property SocialProvider $provider 渠道
 * @property string $openid 开放平台ID
 * @property string $unionid 开放平台UnionID
 * @property string $access_token 访问令牌
 * @property string $refresh_token 刷新令牌
 * @property Carbon $expiry_at 过期时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系属性
 * @property User|Admin $user 用户
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[Table('socials')]
#[Fillable(['user_id', 'user_type', 'provider', 'openid', 'unionid', 'access_token', 'refresh_token', 'expiry_at'])]
#[Hidden(['user_id', 'user_type'])]
class Social extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'user_type' => 'string',
            'provider' => SocialProvider::class,
            'openid' => 'string',
            'unionid' => 'string',
            'access_token' => 'string',
            'refresh_token' => 'string',
            'expiry_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 用户
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 作用域：按所属用户类型筛选（自动兼容 morph map 别名）
     *
     * @param  class-string<Model>|Model  $user  用户类名或用户模型实例
     */
    public function scopeOfUserType(Builder $query, string|Model $user = User::class): Builder
    {
        return $query->whereMorphedTo('user', $user);
    }

    /**
     * 作用域：按渠道与开放平台ID定位社交账号
     */
    public function scopeOfOpenid(Builder $query, SocialProvider $provider, string $openid): Builder
    {
        return $query->where('provider', $provider)->where('openid', $openid);
    }

    /**
     * 更新第三方授权凭证
     *
     * 仅更新本次授权返回的非空字段，避免把已有的 unionid 覆盖为 null 而触发唯一索引冲突。
     */
    public function updateCredentials(UserInterface $socialiteUser): bool
    {
        $expiresIn = $socialiteUser->getExpiresIn();

        return $this->update(array_filter([
            'unionid' => $socialiteUser->getRaw()['unionid'] ?? null,
            'access_token' => $socialiteUser->getAccessToken(),
            'refresh_token' => $socialiteUser->getRefreshToken(),
            'expiry_at' => $expiresIn ? Carbon::now()->addSeconds($expiresIn) : null,
        ]));
    }
}
