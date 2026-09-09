<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Wallet;

use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 钱包流水表
 *
 * @property int $id 流水号
 * @property string $owner_type 持有者类型
 * @property int $owner_id 持有者ID
 * @property float $amount 变动金额
 * @property float $before_balance 变动前可用余额
 * @property float $after_balance 变动后可用余额
 * @property float $before_frozen 变动前冻结余额
 * @property float $after_frozen 变动后冻结余额
 * @property WalletTradeType $type 交易类型
 * @property string|null $source_type 业务来源类型
 * @property int|null $source_id 业务来源ID
 * @property WalletTradeStatus $status 流水状态
 * @property string|null $description 交易描述
 * @property Carbon $created_at 交易时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('wallet_trades')]
#[Fillable([
    'owner_type', 'owner_id', 'amount',
    'before_balance', 'after_balance',
    'before_frozen', 'after_frozen',
    'type', 'source_type', 'source_id',
    'status', 'description',
])]
#[Hidden(['owner_type', 'owner_id'])]
class WalletTrade extends Model
{
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
            'owner_type' => 'string',
            'owner_id' => 'integer',
            'amount' => 'decimal:2',
            'before_balance' => 'decimal:2',
            'after_balance' => 'decimal:2',
            'before_frozen' => 'decimal:2',
            'after_frozen' => 'decimal:2',
            'type' => WalletTradeType::class,
            'source_type' => 'string',
            'source_id' => 'integer',
            'status' => WalletTradeStatus::class,
            'description' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 持有者（多态）
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 业务来源（多态）
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 作用域：列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['owner_type']), fn (Builder $q) => $q->where('owner_type', $filters['owner_type']))
            ->when(! empty($filters['owner_id']), fn (Builder $q) => $q->where('owner_id', (int) $filters['owner_id']))
            ->when(! empty($filters['type']), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['keyword']), fn (Builder $q) => $q->where('description', 'like', "%{$filters['keyword']}%"))
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }
}
