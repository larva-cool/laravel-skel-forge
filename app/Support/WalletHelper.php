<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support;

use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientFrozenBalanceException;
use App\Models\Wallet\WalletTrade;
use Illuminate\Database\Eloquent\Model;

/**
 * 钱包余额引擎
 *
 * 提供真实货币余额的增/减/冻结/解冻/结算原子操作，支持 User/Partner/Agency/Agent 等挂载 HasWalletBalance 的持有者。
 * 所有写操作在事务内行锁，保证并发安全；流水记录前后余额快照用于对账。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletHelper
{
    /**
     * 增加可用余额
     *
     * 适用于充值到账、退款、后台充正等场景。
     */
    public static function incr(Model $owner, float $amount, Model $source, WalletTradeType $type, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($owner, $amount, $type, $desc, $source, function (Model $lockedOwner) use ($amount) {
            $lockedOwner->increment('balance', $amount);

            return $lockedOwner;
        });
    }

    /**
     * 扣减可用余额
     *
     * 适用于直接消费、后台扣减等场景。
     */
    public static function decr(Model $owner, float $amount, Model $source, WalletTradeType $type, string $desc): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($owner, -$amount, $type, $desc, $source, function (Model $lockedOwner) use ($amount) {
            $available = $lockedOwner->balance - $lockedOwner->frozen_balance;
            if ($available < $amount) {
                throw new InsufficientBalanceException(
                    __('user.insufficient_balance', ['balance' => $available])
                );
            }
            $lockedOwner->decrement('balance', $amount);

            return $lockedOwner;
        });
    }

    /**
     * 冻结余额
     *
     * 可用余额减少，冻结余额增加，总资产不变。
     * 适用于下单预授权、提现申请场景。
     */
    public static function freeze(Model $owner, float $amount, Model $source, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($owner, -$amount, WalletTradeType::FREEZE, $desc, $source, function (Model $lockedOwner) use ($amount) {
            $available = $lockedOwner->balance - $lockedOwner->frozen_balance;
            if ($available < $amount) {
                throw new InsufficientBalanceException(
                    __('user.insufficient_balance', ['balance' => $available])
                );
            }
            $lockedOwner->decrement('balance', $amount);
            $lockedOwner->increment('frozen_balance', $amount);

            return $lockedOwner;
        });
    }

    /**
     * 解冻余额
     *
     * 冻结余额归还到可用余额。
     * 适用于订单取消、提现驳回场景。
     */
    public static function unfreeze(Model $owner, float $amount, Model $source, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($owner, $amount, WalletTradeType::UNFREEZE, $desc, $source, function (Model $lockedOwner) use ($amount) {
            if ($lockedOwner->frozen_balance < $amount) {
                throw new InsufficientFrozenBalanceException(
                    __('user.insufficient_frozen_balance', ['frozen' => $lockedOwner->frozen_balance])
                );
            }
            $lockedOwner->increment('balance', $amount);
            $lockedOwner->decrement('frozen_balance', $amount);

            return $lockedOwner;
        });
    }

    /**
     * 结算冻结余额（扣减冻结余额，资金真正流出）
     *
     * 冻结余额减少，可用余额不变，总资产减少。
     * 适用于订单成交扣款、提现打款成功场景。
     */
    public static function settleFrozen(Model $owner, float $amount, Model $source, WalletTradeType $type, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($owner, -$amount, $type, $desc, $source, function (Model $lockedOwner) use ($amount) {
            if ($lockedOwner->frozen_balance < $amount) {
                throw new InsufficientFrozenBalanceException(
                    __('user.insufficient_frozen_balance', ['frozen' => $lockedOwner->frozen_balance])
                );
            }
            $lockedOwner->decrement('frozen_balance', $amount);

            return $lockedOwner;
        });
    }

    /**
     * 获取持有者可用余额
     */
    public static function getBalance(Model $owner): float
    {
        return (float) ($owner->fresh()?->balance ?? 0);
    }

    /**
     * 获取持有者冻结余额
     */
    public static function getFrozenBalance(Model $owner): float
    {
        return (float) ($owner->fresh()?->frozen_balance ?? 0);
    }

    /**
     * 执行钱包余额变更（事务内行锁 + 流水记录）
     *
     * @param  Model  $owner  持有者模型（User/Partner/Agency/Agent）
     * @param  float  $amount  变动金额（正为增加，负为减少）
     * @param  WalletTradeType  $type  交易类型
     * @param  string  $desc  描述
     * @param  Model  $source  业务来源模型
     * @param  callable  $mutator  (Model $lockedOwner): Model  余额变更回调
     */
    private static function execute(Model $owner, float $amount, WalletTradeType $type, string $desc, Model $source, callable $mutator): WalletTrade
    {
        $conn = $owner->newQuery()->getConnection();

        return $conn->transaction(function () use ($owner, $amount, $type, $desc, $source, $mutator) {
            // 行锁取最新余额
            $lockedOwner = $owner->newQuery()->whereKey($owner->getKey())->lockForUpdate()->first();

            if (! $lockedOwner) {
                throw new \InvalidArgumentException('持有者不存在');
            }

            // 记录变更前的余额快照
            $beforeBalance = (float) $lockedOwner->balance;
            $beforeFrozen = (float) $lockedOwner->frozen_balance;

            // 执行余额变更
            $mutator($lockedOwner);

            // 刷新获取最新余额
            $lockedOwner->refresh();
            $afterBalance = (float) $lockedOwner->balance;
            $afterFrozen = (float) $lockedOwner->frozen_balance;

            // 创建流水记录
            $trade = WalletTrade::create([
                'owner_type' => $lockedOwner->getMorphClass(),
                'owner_id' => $lockedOwner->getKey(),
                'amount' => $amount,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'before_frozen' => $beforeFrozen,
                'after_frozen' => $afterFrozen,
                'type' => $type,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'status' => WalletTradeStatus::SUCCESS,
                'description' => $desc,
            ]);

            if ($owner->is($lockedOwner)) {
                $owner->refresh();
            }

            return $trade;
        });
    }
}
