<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Admin;

use App\Enums\AdminStatus;
use App\Models\System\LoginHistory;
use App\Models\System\Social;
use App\Models\Traits\DateTimeFormatter;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * 管理员模型
 *
 * @property int $id 管理员ID
 * @property string $username 用户名
 * @property string|null $email 邮件地址
 * @property string|null $phone 手机号
 * @property string $name 昵称
 * @property string|null $avatar 头像URL
 * @property AdminStatus $status 状态
 * @property string $password 密码哈希
 * @property string $remember_token 记住我 Token
 * @property int $login_count 登录次数
 * @property string|null $last_login_ip 最后登录IP地址
 * @property Carbon|null $last_active_at 最后活动时间
 * @property Carbon|null $last_login_at 最后登录时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 *
 * 关系对象
 * @property Collection<int,LoginHistory> $loginHistories 登录历史
 * @property Collection<int,Role> $roles 角色
 * @property Collection<int,Social> $socials 社交账号
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[Table('admin_users')]
#[Fillable(['username', 'email', 'phone', 'name', 'avatar', 'status', 'password', 'login_count', 'last_login_ip', 'last_login_at', 'last_active_at'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use DateTimeFormatter;
    use HasApiTokens, HasRoles, Notifiable, SoftDeletes;
    use HasConversations;

    /**
     * The guard name for Spatie Permission.
     */
    protected string $guard_name = 'admin';

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'login_count' => 0,
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
            'status' => AdminStatus::class,
            'password' => 'hashed',
            'login_count' => 'integer',
            'last_login_ip' => 'string',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the login histories relation.
     */
    public function loginHistories(): MorphMany
    {
        return $this->morphMany(LoginHistory::class, 'user')->latest('login_at');
    }

    /**
     * Get the social relation.
     */
    public function socials(): MorphMany
    {
        return $this->morphMany(Social::class, 'user')->latest('updated_at');
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
     * 通过账号查找管理员
     */
    public static function findForAccount(string $account): ?Admin
    {
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            return Admin::query()->whereNotNull('email')->where('email', $account)->first();
        } elseif (preg_match('/^1[2-9]\d{9}$/', $account)) {
            return Admin::query()->whereNotNull('phone')->where('phone', $account)->first();
        } else {
            return Admin::query()->whereNotNull('username')->where('username', $account)->first();
        }
    }
}
