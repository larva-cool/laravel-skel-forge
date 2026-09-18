<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Wallet\Recharge;
use App\Models\Wallet\WalletTrade;
use App\Models\Wallet\Withdraw;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 钱包余额能力
 *
 * 任何主表包含 balance / frozen_balance 两列的模型 use 此 trait 即可获得钱包能力。
 * 当前仅 User 模型接入。
 *
 * @property float $balance 可用余额
 * @property float $frozen_balance 冻结余额
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasWalletBalance
{
    /**
     * 钱包流水（作为持有者）
     */
    public function walletTrades(): MorphMany
    {
        return $this->morphMany(WalletTrade::class, 'owner');
    }

    /**
     * 充值订单（作为持有者）
     */
    public function recharges(): MorphMany
    {
        return $this->morphMany(Recharge::class, 'owner');
    }

    /**
     * 提现申请（作为持有者）
     */
    public function withdraws(): MorphMany
    {
        return $this->morphMany(Withdraw::class, 'owner');
    }

    /**
     * 提现收款账户（作为持有者）
     */
    public function withdrawAccounts(): MorphMany
    {
        return $this->morphMany(WithdrawAccount::class, 'owner');
    }

    /**
     * 判断可用余额是否充足
     */
    public function hasBalance(float $amount): bool
    {
        return ($this->balance - $this->frozen_balance) >= $amount;
    }

    /**
     * 总资产（可用 + 冻结）
     */
    public function totalBalance(): float
    {
        return (float) ($this->balance + $this->frozen_balance);
    }
}
