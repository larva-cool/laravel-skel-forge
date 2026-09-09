<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Wallet;

use App\Enums\PayChannel;
use App\Enums\RechargeStatus;
use App\Models\Model;
use App\Policies\RechargeOrderPolicy;
use Database\Factories\Wallet\RechargeOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 充值订单
 *
 * @property int $id 订单ID
 * @property string $owner_type 持有者类型
 * @property int $owner_id 持有者ID
 * @property string $order_no 订单号
 * @property float $amount 充值金额
 * @property float $bonus_amount 赠送金额
 * @property PayChannel $pay_channel 支付渠道
 * @property RechargeStatus $pay_status 支付状态
 * @property string|null $transaction_id 渠道流水号
 * @property int|null $confirmed_by 手动确认的管理员ID
 * @property Carbon|null $paid_at 支付完成时间
 * @property Carbon|null $expired_at 订单过期时间
 * @property string|null $client_ip 下单IP
 * @property string|null $remark 备注
 * @property array|null $callback_payload 渠道原始回调报文
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 *
 * 只读属性
 * @property-read float $total_amount 实际入账金额（充值 + 赠送）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('recharge_orders')]
#[Fillable([
    'owner_type', 'owner_id', 'order_no', 'amount', 'bonus_amount',
    'pay_channel', 'pay_status', 'transaction_id', 'confirmed_by',
    'paid_at', 'expired_at', 'client_ip', 'remark', 'callback_payload',
])]
#[Hidden(['owner_type', 'owner_id'])]
#[UsePolicy(RechargeOrderPolicy::class)]
class RechargeOrder extends Model
{
    /** @use HasFactory<RechargeOrderFactory> */
    use HasFactory;

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
            'order_no' => 'string',
            'amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'pay_channel' => PayChannel::class,
            'pay_status' => RechargeStatus::class,
            'transaction_id' => 'string',
            'confirmed_by' => 'integer',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'client_ip' => 'string',
            'remark' => 'string',
            'callback_payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
     * 实际入账金额（充值金额 + 赠送金额）
     */
    public function totalAmount(): float
    {
        return round((float) $this->amount + (float) $this->bonus_amount, 2);
    }

    /**
     * 是否已超过支付有效期
     */
    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    /**
     * 是否可被确认到账
     *
     * 仅待支付且未过期的订单可确认；已到账订单由幂等逻辑拦截。
     */
    public function canBeConfirmed(): bool
    {
        return $this->pay_status->isPending() && ! $this->isExpired();
    }

    /**
     * 生成唯一订单号
     *
     * 格式：R + YmdHis + 6 位随机大写字母数字
     */
    public static function generateOrderNo(): string
    {
        do {
            $orderNo = 'R'.Carbon::now()->format('YmdHis').Str::upper(Str::random(6));
        } while (static::query()->where('order_no', $orderNo)->exists());

        return $orderNo;
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
            ->when(! empty($filters['pay_status']), fn (Builder $q) => $q->where('pay_status', $filters['pay_status']))
            ->when(! empty($filters['pay_channel']), fn (Builder $q) => $q->where('pay_channel', $filters['pay_channel']))
            ->when(! empty($filters['order_no']), fn (Builder $q) => $q->where('order_no', 'like', "%{$filters['order_no']}%"))
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }
}
