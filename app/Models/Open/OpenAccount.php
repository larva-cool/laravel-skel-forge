<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Open;

use App\Enums\IdentityStatus;
use App\Enums\IdentityType;
use App\Enums\UserStatus;
use App\Models\Traits\DateTimeFormatter;
use App\Models\Traits\HasWalletBalance;
use Database\Factories\Open\OpenAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

/**
 * 开放平台账户（外部合作方，独立鉴权主体）
 *
 * 不关联平台内部业务主体；通过 Sanctum Personal Access Token 鉴权，
 * 一个账户同一时刻仅保留一个有效 Token（重置即轮换）。
 *
 * @property int $id 账户ID
 * @property string|null $username 用户名
 * @property string|null $email 邮箱
 * @property string|null $phone 手机号
 * @property string|null $name 名称
 * @property string|null $contact_name 联系人
 * @property string|null $contact_phone 联系电话
 * @property string|null $contact_email 联系邮箱
 * @property int $rate_limit 每分钟请求上限（0 使用全局默认）
 * @property int|null $available_points 可用积分
 * @property int|null $available_coins 可用金币
 * @property float $balance 可用余额（真实货币，元）
 * @property float $frozen_balance 冻结余额（真实货币，元）
 * @property UserStatus $status 账户状态（正常/已冻结）
 * @property string|null $password 密码
 * @property string|null $remember_token 记住我令牌
 * @property int|null $login_count 登录次数
 * @property string|null $last_login_ip 最后登录IP
 * @property Carbon|null $last_active_at 最后活动时间
 * @property Carbon|null $last_login_at 最后登录时间
 * @property string|null $identity_type 实名认证类型
 * @property bool $identity_verified 是否已通过实名认证（逻辑属性，由 identity_status 派生）
 * @property IdentityStatus $identity_status 实名认证状态
 * @property Carbon|null $created_at 注册时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('open_accounts')]
#[Fillable([
    'username', 'email', 'phone', 'name', 'contact_name', 'contact_phone', 'contact_email',
    'rate_limit', 'available_points', 'available_coins', 'balance', 'frozen_balance',
    'status', 'password', 'remember_token', 'login_count', 'last_login_ip', 'last_active_at', 'last_login_at', 'identity_type', 'identity_status',
])]
#[Hidden(['deleted_at'])]
class OpenAccount extends Authenticatable
{
    use DateTimeFormatter;
    use HasApiTokens;

    /** @use HasFactory<OpenAccountFactory> */
    use HasFactory;

    use HasWalletBalance;
    use SoftDeletes;

    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rate_limit' => 60,
        'available_points' => 0,
        'available_coins' => 0,
        'balance' => 0,
        'frozen_balance' => 0,
        'status' => UserStatus::FROZEN,
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
            'contact_name' => 'string',
            'contact_phone' => 'string',
            'contact_email' => 'string',
            'rate_limit' => 'integer',
            'available_points' => 'integer',
            'available_coins' => 'integer',
            'balance' => 'decimal:2',
            'frozen_balance' => 'decimal:2',
            'status' => UserStatus::class,
            'identity_type' => IdentityType::class,
            'identity_status' => IdentityStatus::class,
            'password' => 'hashed',
            'remember_token' => 'string',
            'login_count' => 'integer',
            'last_login_ip' => 'string',
            'last_active_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_request_at' => 'datetime',
            'last_request_ip' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 是否为正常状态（账号开关）
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * 标记为正常
     */
    public function markActive(): bool
    {
        return $this->updateQuietly(['status' => UserStatus::ACTIVE]);
    }

    /**
     * 标记为已冻结
     */
    public function markFrozen(): bool
    {
        return $this->updateQuietly(['status' => UserStatus::FROZEN]);
    }

    /**
     * 是否已通过实名认证
     */
    public function isVerified(): bool
    {
        return $this->identity_status === IdentityStatus::APPROVED;
    }

    /**
     * identity_verified 访问器（逻辑属性，由 identity_status 派生）
     */
    protected function identityVerified(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => ($attributes['identity_status'] ?? null) === IdentityStatus::APPROVED->value
        );
    }

    /**
     * 轮换访问令牌：作废历史令牌并签发新令牌
     *
     * 明文 Token 仅在返回值中出现一次，之后不可再查。
     */
    public function rotateToken(string $name = 'open-api'): NewAccessToken
    {
        $this->tokens()->delete();

        return $this->createToken($name);
    }
}
