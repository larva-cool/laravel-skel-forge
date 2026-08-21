<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\User;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * 用户统计模型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('user_stats')]
#[Fillable([
    'stat_date',
    'total_user_count',
    'new_user_count',
    'active_user_count',
    'total_point_count',
    'incr_point_count',
    'decr_point_count',
    'total_coin_count',
    'incr_coin_count',
    'decr_coin_count',
])]
class UserStat extends Model
{
    // 时间定义
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'total_user_count' => 0,
        'new_user_count' => 0,
        'active_user_count' => 0,
        'total_point_count' => 0,
        'incr_point_count' => 0,
        'decr_point_count' => 0,
        'total_coin_count' => 0,
        'incr_coin_count' => 0,
        'decr_coin_count' => 0,
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
            'stat_date' => 'date:Y-m-d',
            'total_user_count' => 'integer',
            'new_user_count' => 'integer',
            'active_user_count' => 'integer',
            'total_point_count' => 'integer',
            'incr_point_count' => 'integer',
            'decr_point_count' => 'integer',
            'total_coin_count' => 'integer',
            'incr_coin_count' => 'integer',
            'decr_coin_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
