<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Coin;

use App\Enums\CoinType;
use App\Models\Model;
use App\Models\Traits\HasUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 金币交易流水表
 *
 * @property int $id 流水号
 * @property int $user_id 用户ID
 * @property int $coins 交易金币数量
 * @property string $description 交易描述
 * @property CoinType $type 交易类型
 * @property int $source_id 关联模型ID
 * @property string $source_type 关联模型类型
 * @property Carbon $created_at 添加时间
 * @property-read string $type_label 类型标签
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('coin_trades')]
#[Fillable(['user_id', 'coins', 'description', 'type', 'source_id', 'source_type'])]
#[Hidden(['user_id'])]
class CoinTrade extends Model
{
    use HasUser;
    public const UPDATED_AT = null;

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
            'coins' => 'integer',
            'description' => 'string',
            'type' => CoinType::class,
            'source_id' => 'integer',
            'source_type' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the source entity that the Transaction belongs to.
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 获取用户当前金币数
     */
    public static function getCurrentCoins(int $userId): int
    {
        return (int) self::where('user_id', $userId)->sum('coins');
    }

    /**
     * 修复用户金币余额
     */
    public static function fixCurrentCoins(int $userId): bool
    {
        $sumCoins = self::getCurrentCoins($userId);
        $updated = \App\Models\User::where('id', $userId)->update(['available_coins' => max(0, $sumCoins)]);

        return (bool) $updated;
    }
}
