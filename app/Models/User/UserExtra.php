<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\User;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 用户扩展信息
 *
 * @property int $user_id 用户ID
 * @property int $username_change_count 用户名修改次数
 * @property Carbon|null $email_verified_at 邮箱验证时间
 *
 * 关系对象
 * @property User $user 用户实例
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('user_extras', 'user_id', null, false, false)]
#[Fillable(['username_change_count', 'email_verified_at'])]
#[Hidden(['user_id'])]
class UserExtra extends Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'username_change_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'username_change_count' => 'integer',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the user relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
