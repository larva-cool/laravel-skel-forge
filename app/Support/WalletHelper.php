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
use App\Models\User;
use App\Models\Wallet\WalletTrade;
use Illuminate\Database\Eloquent\Model;

/**
 * 钱包余额引擎
 *
 * 提供真实货币余额的增/减/冻结/解冻/结算原子操作。
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
    public static function incr(User $user, float $amount, Model $source, WalletTradeType $type, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($user, $amount, $type, $desc, $source, function (User $lockedUser) use ($amount) {
            $lockedUser->increment('balance', $amount);

            return $lockedUser;
        });
    }

    /**
     * 扣减可用余额
     *
     * 适用于直接消费、后台扣减等场景。
     */
    public static function decr(User $user, float $amount, Model $source, WalletTradeType $type, string $desc): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($user, -$amount, $type, $desc, $source, function (User $lockedUser) use ($amount) {
            $available = $lockedUser->balance - $lockedUser->frozen_balance;
            if ($available < $amount) {
                throw new InsufficientBalanceException(
                    __('user.insufficient_balance', ['balance' => $available])
                );
            }
            $lockedUser->decrement('balance', $amount);

            return $lockedUser;
        });
    }

    /**
     * 冻结余额
     *
     * 可用余额减少，冻结余额增加，总资产不变。
     * 适用于下单预授权、提现申请场景。
     */
    public static function freeze(User $user, float $amount, Model $source, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($user, -$amount, WalletTradeType::FREEZE, $desc, $source, function (User $lockedUser) use ($amount) {
            $available = $lockedUser->balance - $lockedUser->frozen_balance;
            if ($available < $amount) {
                throw new InsufficientBalanceException(
                    __('user.insufficient_balance', ['balance' => $available])
                );
            }
            $lockedUser->decrement('balance', $amount);
            $lockedUser->increment('frozen_balance', $amount);

            return $lockedUser;
        });
    }

    /**
     * 解冻余额
     *
     * 冻结余额归还到可用余额。
     * 适用于订单取消、提现驳回场景。
     */
    public static function unfreeze(User $user, float $amount, Model $source, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($user, $amount, WalletTradeType::UNFREEZE, $desc, $source, function (User $lockedUser) use ($amount) {
            if ($lockedUser->frozen_balance < $amount) {
                throw new InsufficientFrozenBalanceException(
                    __('user.insufficient_frozen_balance', ['frozen' => $lockedUser->frozen_balance])
                );
            }
            $lockedUser->increment('balance', $amount);
            $lockedUser->decrement('frozen_balance', $amount);

            return $lockedUser;
        });
    }

    /**
     * 结算冻结余额（扣减冻结余额，资金真正流出）
     *
     * 冻结余额减少，可用余额不变，总资产减少。
     * 适用于订单成交扣款、提现打款成功场景。
     */
    public static function settleFrozen(User $user, float $amount, Model $source, WalletTradeType $type, string $desc = ''): WalletTrade
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须为正数');
        }

        return self::execute($user, -$amount, $type, $desc, $source, function (User $lockedUser) use ($amount) {
            if ($lockedUser->frozen_balance < $amount) {
                throw new InsufficientFrozenBalanceException(
                    __('user.insufficient_frozen_balance', ['frozen' => $lockedUser->frozen_balance])
                );
            }
            $lockedUser->decrement('frozen_balance', $amount);

            return $lockedUser;
        });
    }

    /**
     * 获取用户可用余额
     */
    public static function getBalance(User|int $user): float
    {
        $userId = $user instanceof User ? $user->id : $user;
        $user = User::query()->find($userId);

        return (float) ($user?->balance ?? 0);
    }

    /**
     * 获取用户冻结余额
     */
    public static function getFrozenBalance(User|int $user): float
    {
        $userId = $user instanceof User ? $user->id : $user;
        $user = User::query()->find($userId);

        return (float) ($user?->frozen_balance ?? 0);
    }

    /**
     * 执行钱包余额变更（事务内行锁 + 流水记录）
     *
     * @param  User  $user  用户
     * @param  float  $amount  变动金额（正为增加，负为减少）
     * @param  WalletTradeType  $type  交易类型
     * @param  string  $desc  描述
     * @param  Model  $source  业务来源模型
     * @param  callable  $mutator  (User $lockedUser): User  余额变更回调
     */
    private static function execute(User $user, float $amount, WalletTradeType $type, string $desc, Model $source, callable $mutator): WalletTrade
    {
        $conn = User::query()->getConnection();

        return $conn->transaction(function () use ($user, $amount, $type, $desc, $source, $mutator) {
            // 行锁取最新余额
            $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->first();

            if (! $lockedUser) {
                throw new \InvalidArgumentException('用户不存在');
            }

            // 记录变更前的余额快照
            $beforeBalance = (float) $lockedUser->balance;
            $beforeFrozen = (float) $lockedUser->frozen_balance;

            // 执行余额变更
            $mutator($lockedUser);

            // 刷新获取最新余额
            $lockedUser->refresh();
            $afterBalance = (float) $lockedUser->balance;
            $afterFrozen = (float) $lockedUser->frozen_balance;

            // 创建流水记录
            $trade = WalletTrade::create([
                'owner_type' => 'user',
                'owner_id' => $lockedUser->id,
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

            if ($user->id === $lockedUser->id) {
                $user->refresh();
            }

            return $trade;
        });
    }
}
