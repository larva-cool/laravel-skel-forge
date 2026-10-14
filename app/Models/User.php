<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models;

use App\Contracts\IdentityVerifiable;
use App\Enums\IdentityStatus;
use App\Enums\IdentityType;
use App\Enums\UserStatus;
use App\Events\User\EmailReset;
use App\Events\User\EmailVerified;
use App\Events\User\PhoneReset;
use App\Events\User\UsernameReset;
use App\Models\Coin\CoinTrade;
use App\Models\Point\PointTrade;
use App\Models\System\LoginHistory;
use App\Models\System\Social;
use App\Models\Traits\DateTimeFormatter;
use App\Models\Traits\HasIdentity;
use App\Models\Traits\HasInvitation;
use App\Models\Traits\HasReportable;
use App\Models\Traits\HasTaskProgress;
use App\Models\Traits\HasWalletBalance;
use App\Models\User\IdentityVerification;
use App\Models\User\UserExtra;
use App\Models\User\UserProfile;
use App\Models\UserSegment\Membership;
use App\Observers\UserObserver;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

/**
 * 用户模型
 *
 * @property int $id 用户ID
 * @property string|null $username 用户名
 * @property string|null $email 邮箱
 * @property string|null $phone 手机号
 * @property string|null $name 昵称
 * @property string|null $avatar 头像
 * @property UserStatus $status 状态
 * @property int|null $available_points 可用积分
 * @property int|null $available_coins 可用金币
 * @property float $balance 可用余额（真实货币，元）
 * @property float $frozen_balance 冻结余额（真实货币，元）
 * @property string|null $password 密码
 * @property string|null $remember_token 记住我令牌
 * @property int|null $login_count 登录次数
 * @property string|null $last_login_ip 最后登录IP
 * @property Carbon|null $vip_expires_at VIP过期时间
 * @property Carbon|null $last_active_at 最后活动时间
 * @property Carbon|null $last_login_at 最后登录时间
 * @property string|null $identity_type 实名认证类型
 * @property bool $identity_verified 是否已通过实名认证（逻辑属性，由 identity_status 派生）
 * @property IdentityStatus $identity_status 实名认证状态
 * @property Carbon|null $created_at 注册时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 *
 * 只读属性
 * @property-read string|null $phone_text 手机号文本
 * @property-read string $status_label 状态文本
 * @property-read IdentityType|null $identity_type_enum 实名认证类型枚举
 * @property-read \App\Models\User\IdentityVerification|null $latest_identity 最新认证记录
 *
 * 关系对象
 * @property UserProfile $profile 用户资料
 * @property UserExtra $extra 用户扩展信息
 * @property Collection<int,PointTrade> $points 积分交易明细
 * @property Collection<int,CoinTrade> $coins 金币交易明细
 * @property Collection<int,\App\Models\Wallet\WalletTrade> $walletTrades 钱包流水明细
 * @property Collection<int,\App\Models\Wallet\Recharge> $recharges 充值订单
 * @property Collection<int,\App\Models\Wallet\Withdraw> $withdraws 提现申请
 * @property Collection<int,LoginHistory> $loginHistories 登录历史
 * @property Collection<int,Social> $socials 社交账号
 * @property Collection<int,IdentityVerification> $identityVerifications 实名认证记录
 * @property \Illuminate\Database\Eloquent\Collection<int,\App\Models\UserSegment\Segment> $segments 用户分群
 * @property \Illuminate\Database\Eloquent\Collection<int,Membership> $memberships 分群成员记录
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('users')]
#[Fillable(['username', 'name', 'email', 'phone', 'avatar', 'status', 'available_points', 'available_coins', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy([UserObserver::class])]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable implements IdentityVerifiable, MustVerifyEmail
{
    use DateTimeFormatter;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, Searchable, SoftDeletes;

    use HasIdentity;
    use HasInvitation;
    use HasReportable;
    use HasTaskProgress;
    use HasWalletBalance;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => UserStatus::ACTIVE,
        'available_points' => 0,
        'available_coins' => 0,
        'balance' => 0,
        'frozen_balance' => 0,
        'vip_expires_at' => null,
        'identity_status' => IdentityStatus::UNSUBMITTED,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'username' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'name' => 'string',
            'avatar' => 'string',
            'status' => UserStatus::class,
            'available_points' => 'integer',
            'available_coins' => 'integer',
            'balance' => 'decimal:2',
            'frozen_balance' => 'decimal:2',
            'password' => 'hashed',
            'remember_token' => 'string',
            'login_count' => 'integer',
            'last_login_ip' => 'string',
            'vip_expires_at' => 'datetime',
            'identity_type' => IdentityType::class,
            'identity_status' => IdentityStatus::class,
            'last_active_at' => 'datetime',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 获取昵称
     */
    protected function name(): Attribute
    {
        return Attribute::make(get: function (?string $value, $attributes) {
            return $value ?: $attributes['username'];
        });
    }

    /**
     * 获取手机号
     */
    protected function phoneText(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, $attributes) => mobile_replace($attributes['phone'])
        )->shouldCache();
    }

    /**
     * 获取状态标签
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status?->label() ?? ''
        )->shouldCache();
    }

    /**
     * Get the profile relation.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'id');
    }

    /**
     * Get the extra relation.
     */
    public function extra(): HasOne
    {
        return $this->hasOne(UserExtra::class, 'user_id', 'id');
    }

    /**
     * Get the point trades relation.
     */
    public function points(): HasMany
    {
        return $this->hasMany(PointTrade::class)->latest('id');
    }

    /**
     * Get the coin trades relation.
     */
    public function coins(): HasMany
    {
        return $this->hasMany(CoinTrade::class)->latest('id');
    }

    /**
     * Get the social relation.
     */
    public function socials(): MorphMany
    {
        return $this->morphMany(Social::class, 'user')->latest('updated_at');
    }

    /**
     * Get the login histories relation.
     */
    public function loginHistories(): MorphMany
    {
        return $this->morphMany(LoginHistory::class, 'user')->latest('login_at');
    }

    /**
     * Get the segments relation.
     */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\UserSegment\Segment::class,
            'user_segment_memberships',
            'user_id',
            'segment_id'
        )->withPivot(['source', 'created_at']);
    }

    /**
     * 分群成员记录（一对多到 Membership）
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * 手机号路由通知
     *
     * @param  \Illuminate\Notifications\Notification|null  $notification
     */
    public function routeNotificationForPhone($notification): ?string
    {
        return $this->phone ?: null;
    }

    /**
     * 是否有头像
     */
    public function hasAvatar(): bool
    {
        return ! empty($this->getRawOriginal('avatar'));
    }

    /**
     * 是否有密码
     */
    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        $this->loadMissing('extra');

        return ! is_null($this->extra->email_verified_at);
    }

    /**
     * 确定模型是否应可搜索。
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->status->isFrozen();
    }

    /**
     * 获取与模型关联的索引名称。
     */
    public function searchableAs(): string
    {
        return 'users_index';
    }

    /**
     * 可搜索数据类型
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('profile');

        return [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'email' => (string) $this->email,
            'phone' => (string) $this->phone,
            'name' => (string) $this->name,
            'status' => $this->status,
            'vip_expires_at' => $this->vip_expires_at,
            'created_at' => $this->created_at,
            'gender' => $this->profile->gender,
            'birthday' => $this->profile->birthday,
            'province_id' => $this->profile->province_id,
            'city_id' => $this->profile->city_id,
            'district_id' => $this->profile->district_id,
            'intro' => $this->profile->intro,
            'bio' => $this->profile->bio,
        ];
    }

    /**
     * 是否是VIP会员
     */
    public function isVip(): bool
    {
        return $this->vip_expires_at && $this->vip_expires_at->isFuture();
    }

    /**
     * 增加 Vip 天数
     */
    public function addVipDays(int|string $days): bool
    {
        if ($this->isVip()) {
            $this->vip_expires_at = $this->vip_expires_at->addDays($days);
        } else {
            $this->vip_expires_at = Carbon::now()->addDays((int) $days);
        }

        return $this->saveQuietly();
    }

    /**
     * Mark the given user's email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        $this->loadMissing('extra');
        $status = $this->extra->forceFill(['email_verified_at' => $this->freshTimestamp()])->saveQuietly();
        Event::dispatch(new EmailVerified($this));

        return $status;
    }

    /**
     * 重置用户名
     */
    public function resetUsername(string $username): void
    {
        $this->loadMissing('extra');

        if ($username != $this->username) {
            $this->update(['username' => $username]);
            $this->extra->increment('username_change_count', 1, []);
            Event::dispatch(new UsernameReset($this));
        }
    }

    /**
     * 重置用户密码
     */
    public function resetPassword(string $password): void
    {
        $this->password = $password;
        $this->setRememberToken(Str::random(60));
        $this->saveQuietly();
        Event::dispatch(new PasswordReset($this));
    }

    /**
     * Mark the given user's active.
     */
    public function markActive(): bool
    {
        return $this->updateQuietly(['status' => UserStatus::ACTIVE]);
    }

    /**
     * Mark the given user's frozen.
     */
    public function markFrozen(): bool
    {
        return $this->updateQuietly(['status' => UserStatus::FROZEN]);
    }

    /**
     * 刷新最后活动时间
     */
    public function refreshLastActiveAt(): void
    {
        if (empty($this->last_active_at) || $this->last_active_at->lt(Carbon::now()->subMinutes(5))) {
            $this->updateQuietly(['last_active_at' => Carbon::now()]);
        }
    }

    /**
     * 重置用户手机号
     */
    public function resetPhone(int|string $phone): bool
    {
        $status = $this->forceFill(['phone' => $phone])->saveQuietly();
        Event::dispatch(new PhoneReset($this));

        return $status;
    }

    /**
     * 重置用户邮箱
     */
    public function resetEmail(string $email): bool
    {
        $this->loadMissing('extra');

        $status = $this->forceFill(['email' => $email])->saveQuietly();
        $this->extra->forceFill(['email_verified_at' => null])->saveQuietly();
        Event::dispatch(new EmailReset($this));

        return $status;
    }
}
