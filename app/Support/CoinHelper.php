<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support;

use App\Enums\CoinType;
use App\Exceptions\InsufficientCoinsException;
use App\Models\Coin\CoinTrade;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * 金币助手类 - 提供用户金币管理相关功能
 *
 * 该类封装了金币的增加、减少和金币总额更新等核心功能。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CoinHelper
{
    /**
     * 增加金币
     *
     * @param  int|string|User  $user  用户ID或用户模型
     * @param  int  $coins  增加的金币数
     * @param  Model  $source  来源模型
     * @param  CoinType  $type  交易类型
     * @param  string  $desc  交易描述
     * @return CoinTrade 创建的金币交易记录模型实例
     */
    public static function incr(int|string|User $user, int $coins, Model $source, CoinType $type, string $desc = ''): CoinTrade
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('金币数量必须为正数');
        }

        $trade = self::createTrade(self::resolveUserId($user), $coins, $source->getKey(), $source->getMorphClass(), $type, $desc);

        if ($user instanceof User) {
            $user->refresh();
        }

        return $trade;
    }

    /**
     * 扣除金币
     *
     * @param  int|string|User  $user  用户ID或用户模型
     * @param  int  $coins  扣除的金币数
     * @param  Model  $source  来源模型
     * @param  CoinType  $type  交易类型
     * @param  string  $desc  交易描述
     * @return bool 操作是否成功
     *
     * @throws InsufficientCoinsException 当金币不足时抛出
     */
    public static function decr(int|string|User $user, int $coins, Model $source, CoinType $type, string $desc): bool
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('金币数量必须为正数');
        }

        $userId = self::resolveUserId($user);

        // 检查金币是否足够
        $currentCoins = self::getCurrentCoins($userId);
        if ($currentCoins < $coins) {
            throw new InsufficientCoinsException(__('user.insufficient_coins', ['coins' => $currentCoins]));
        }

        // 创建金币交易记录
        self::createTrade($userId, -$coins, $source->getKey(), $source->getMorphClass(), $type, $desc);

        if ($user instanceof User) {
            $user->refresh();
        }

        return true;
    }

    /**
     * 解析用户ID
     */
    protected static function resolveUserId(int|string|User $user): int
    {
        return (int) ($user instanceof User ? $user->id : $user);
    }

    /**
     * 获取用户当前金币余额
     */
    public static function getCurrentCoins(int|string $userId): int
    {
        return CoinTrade::getCurrentCoins((int) $userId);
    }

    /**
     * 修复当前用户可用金币余额
     */
    public static function fixCurrentCoins(int|string $userId): bool
    {
        return CoinTrade::fixCurrentCoins((int) $userId);
    }

    /**
     * 更新用户可用金币总额
     *
     * 重新计算用户当前可用金币并更新到用户表
     */
    public static function updateCoinTotal(int|string $userId): void
    {
        $sumCoins = self::getCurrentCoins($userId);
        User::where('id', $userId)->update(['available_coins' => max(0, $sumCoins)]);
    }

    /**
     * 创建金币交易记录
     *
     * @param  int|string  $userId  用户ID
     * @param  int  $coins  交易金币数（正增加，负减少）
     * @param  int|string  $sourceId  来源ID
     * @param  string  $sourceType  来源类型
     * @param  CoinType  $type  交易类型
     * @param  string  $desc  交易描述
     *
     * @throws InsufficientCoinsException
     * @throws \Throwable
     */
    private static function createTrade(int|string $userId, int $coins, int|string $sourceId, string $sourceType, CoinType $type, string $desc): CoinTrade
    {
        $conn = CoinTrade::query()->getConnection();
        $conn->beginTransaction();
        try {
            if (! User::query()->whereKey($userId)->exists()) {
                throw new \InvalidArgumentException('用户不存在');
            }

            // 检查金币是否足够
            $currentCoins = self::getCurrentCoins($userId);
            if ($coins < 0 && $currentCoins + $coins < 0) {
                throw new InsufficientCoinsException(__('user.insufficient_coins', ['coins' => $currentCoins]));
            }

            /** @var CoinTrade $trade */
            $trade = CoinTrade::create([
                'user_id' => (int) $userId,
                'coins' => $coins,
                'source_id' => $sourceId,
                'source_type' => $sourceType,
                'type' => $type,
                'description' => $desc,
            ]);

            // 按流水重新汇总并更新用户可用金币总额
            self::updateCoinTotal($userId);

            $conn->commit();

            return $trade;
        } catch (\Exception $e) {
            $conn->rollBack();
            Log::error('创建金币交易记录失败', ['exception' => $e]);
            throw $e;
        }
    }
}
