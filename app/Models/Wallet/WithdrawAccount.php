<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Wallet;

use App\Enums\WithdrawAccountStatus;
use App\Enums\WithdrawAccountType;
use App\Models\Model;
use App\Policies\WithdrawAccountPolicy;
use Database\Factories\Wallet\WithdrawAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 提现收款账户
 *
 * @property int $id 账户ID
 * @property string $owner_type 持有者类型
 * @property int $owner_id 持有者ID
 * @property WithdrawAccountType $account_type 账户类型
 * @property string $real_name 真实姓名
 * @property string $account_no 收款账号
 * @property string|null $bank_name 开户银行
 * @property string|null $bank_branch 支行名称
 * @property bool $is_default 是否默认账户
 * @property bool $is_verified 是否已验证
 * @property Carbon|null $last_used_at 最后使用时间
 * @property WithdrawAccountStatus $status 状态
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('withdraw_accounts')]
#[Fillable([
    'owner_type', 'owner_id', 'account_type', 'real_name', 'account_no',
    'bank_name', 'bank_branch', 'is_default', 'is_verified', 'last_used_at', 'status',
])]
#[Hidden(['owner_type', 'owner_id', 'account_no'])]
#[UsePolicy(WithdrawAccountPolicy::class)]
class WithdrawAccount extends Model
{
    /** @use HasFactory<WithdrawAccountFactory> */
    use HasFactory;

    use SoftDeletes;

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
            'account_type' => WithdrawAccountType::class,
            'real_name' => 'string',
            'account_no' => 'string',
            'bank_name' => 'string',
            'bank_branch' => 'string',
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'last_used_at' => 'datetime',
            'status' => WithdrawAccountStatus::class,
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
     * 是否可用
     */
    public function isAvailable(): bool
    {
        return $this->status->isActive();
    }

    /**
     * 脱敏账号展示
     */
    public function maskedAccountNo(): string
    {
        $no = $this->account_no;
        $len = mb_strlen($no);

        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return mb_substr($no, 0, 2).str_repeat('*', $len - 6).mb_substr($no, -4);
    }

    /**
     * 标记使用
     */
    public function markUsed(): void
    {
        $this->update(['last_used_at' => Carbon::now()]);
    }

    /**
     * 设为默认（同持有者同类型下互斥）
     */
    public function setDefault(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('owner_type', $this->owner_type)
                ->where('owner_id', $this->owner_id)
                ->where('account_type', $this->account_type)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }

    /**
     * 转为提现快照数组（提交提现时冻结一份到 withdraws.account_info）
     *
     * @return array<string, mixed>
     */
    public function toAccountInfoArray(): array
    {
        $info = [
            'account' => $this->account_no,
            'real_name' => $this->real_name,
        ];

        if ($this->account_type === WithdrawAccountType::BANK) {
            $info['bank_name'] = $this->bank_name;
            $info['bank_branch'] = $this->bank_branch;
        }

        return $info;
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
            ->when(! empty($filters['account_type']), fn (Builder $q) => $q->where('account_type', $filters['account_type']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(isset($filters['is_default']), fn (Builder $q) => $q->where('is_default', (bool) $filters['is_default']));
    }
}
