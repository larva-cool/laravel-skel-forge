<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Wallet;

use App\Enums\WithdrawAccountType;
use App\Enums\WithdrawStatus;
use App\Models\Model;
use App\Policies\WithdrawPolicy;
use Database\Factories\Wallet\WithdrawFactory;
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
 * 提现申请
 *
 * @property int $id 提现单ID
 * @property string $owner_type 持有者类型
 * @property int $owner_id 持有者ID
 * @property string $order_no 提现单号
 * @property float $amount 申请提现金额
 * @property float $fee 手续费
 * @property float $actual_amount 实际到账金额
 * @property WithdrawAccountType $account_type 收款账户类型
 * @property array $account_info 收款账户信息
 * @property WithdrawStatus $status 提现状态
 * @property int|null $reviewed_by 审核管理员ID
 * @property Carbon|null $reviewed_at 审核时间
 * @property string|null $reject_reason 驳回原因
 * @property Carbon|null $transfer_at 打款时间
 * @property string|null $fail_reason 打款失败原因
 * @property string|null $remark 备注
 * @property string|null $client_ip 下单IP
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('withdraws')]
#[Fillable([
    'owner_type', 'owner_id', 'order_no', 'amount', 'fee', 'actual_amount',
    'account_type', 'account_info', 'status', 'reviewed_by', 'reviewed_at',
    'reject_reason', 'transfer_at', 'fail_reason', 'remark', 'client_ip',
])]
#[Hidden(['owner_type', 'owner_id'])]
#[UsePolicy(WithdrawPolicy::class)]
class Withdraw extends Model
{
    /** @use HasFactory<WithdrawFactory> */
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
            'fee' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'account_type' => WithdrawAccountType::class,
            'account_info' => 'array',
            'status' => WithdrawStatus::class,
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
            'reject_reason' => 'string',
            'transfer_at' => 'datetime',
            'fail_reason' => 'string',
            'remark' => 'string',
            'client_ip' => 'string',
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
     * 是否待审核
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * 是否已审核通过（待打款）
     */
    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    /**
     * 生成唯一提现单号
     *
     * 格式：W + YmdHis + 6 位随机大写字母数字
     */
    public static function generateOrderNo(): string
    {
        do {
            $orderNo = 'W'.Carbon::now()->format('YmdHis').Str::upper(Str::random(6));
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
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['account_type']), fn (Builder $q) => $q->where('account_type', $filters['account_type']))
            ->when(! empty($filters['order_no']), fn (Builder $q) => $q->where('order_no', 'like', "%{$filters['order_no']}%"))
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }
}
