<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support;

use App\Enums\PointType;
use App\Exceptions\InsufficientPointsException;
use App\Models\Point\PointRecord;
use App\Models\Point\PointTrade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 积分助手类 - 提供用户积分管理相关功能
 *
 * 该类封装了积分的增加、减少、过期处理以及积分总额更新等核心功能，
 * 遵循先过期先使用的原则处理积分消耗，并提供异常处理机制。
 *
 * 所有写操作均在数据库事务中执行，并对参与计算的积分记录加行锁，
 * 以避免并发场景下的超扣与积分总额漂移。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PointHelper
{
    /**
     * 遍历积分记录时每批读取的条数
     */
    protected const CHUNK_SIZE = 100;

    /**
     * 空过期时间的排序哨兵值（视为永不过期，排在最后消耗）
     */
    protected const NEVER_EXPIRE_AT = '9999-12-31 23:59:59';

    /**
     * 增加用户积分
     *
     * 创建积分交易记录并更新用户积分总额
     *
     * @param  int|string  $userId  用户ID
     * @param  int  $points  增加的积分数量，必须为正数
     * @param  Model  $source  积分来源模型
     * @param  PointType  $type  交易类型，使用本类的TYPE_*常量
     * @param  string  $desc  交易描述
     * @return PointTrade 创建的积分交易记录模型实例
     *
     * @throws \InvalidArgumentException 当积分数量非正数时抛出
     */
    public static function incr(int|string $userId, int $points, Model $source, PointType $type, string $desc = ''): PointTrade
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('积分数量必须为正数');
        }

        // 计算积分过期时间（默认365天后）
        $expireTime = Carbon::now()->addDays((int) settings('user.point_expiration', 365));

        return self::connection()->transaction(
            fn () => self::createTradeLog($userId, $points, $source->getKey(), $source->getMorphClass(), $type, $desc, $expireTime)
        );
    }

    /**
     * 减少用户积分
     *
     * 根据先过期先使用的原则消耗用户积分，积分不足时抛出异常
     *
     * @param  int|string  $userId  用户ID
     * @param  int  $point  要减少的积分数量，必须为正数
     * @param  Model  $source  积分消耗来源模型
     * @param  PointType  $type  交易类型，使用本类的TYPE_*常量
     * @param  string  $desc  交易描述
     * @return bool 操作是否成功
     *
     * @throws \InvalidArgumentException 当积分数量非正数时抛出
     * @throws InsufficientPointsException 当积分不足时抛出
     */
    public static function decr(int|string $userId, int $point, Model $source, PointType $type, string $desc): bool
    {
        if ($point <= 0) {
            throw new \InvalidArgumentException('积分数量必须为正数');
        }

        return self::connection()->transaction(function () use ($userId, $point, $source, $type, $desc): bool {
            $sumPoint = 0;            // 已锁定的可用积分累计值
            $consumedIds = [];        // 被完整或部分消耗的积分记录ID
            $targetRecord = null;     // 满足扣减所需的最后一条积分记录
            $cursorExpiredAt = null;  // 键集游标：上一批最后一条记录的过期时间
            $cursorId = null;         // 键集游标：上一批最后一条记录的ID

            // 按「过期时间 + ID」键集分页遍历，游标与排序键一致，避免重复累加或漏读
            while ($targetRecord === null) {
                $records = self::availableRecords($userId)
                    ->when($cursorId !== null, fn (Builder $query) => $query->whereRaw(
                        '('.self::expireOrderExpression().' > ? or ('.self::expireOrderExpression().' = ? and id > ?))',
                        [$cursorExpiredAt, $cursorExpiredAt, $cursorId]
                    ))
                    ->orderByRaw(self::expireOrderExpression().' asc')
                    ->orderBy('id')
                    ->limit(self::CHUNK_SIZE)
                    ->lockForUpdate()
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                /** @var PointRecord $record */
                foreach ($records as $record) {
                    $sumPoint += $record->points;
                    $consumedIds[] = $record->id;

                    if ($sumPoint >= $point) {
                        $targetRecord = $record;
                        break;
                    }
                }

                /** @var PointRecord $lastRecord */
                $lastRecord = $records->last();
                $cursorExpiredAt = $lastRecord->expired_at?->toDateTimeString() ?? self::NEVER_EXPIRE_AT;
                $cursorId = $lastRecord->id;

                if ($records->count() < self::CHUNK_SIZE) {
                    break;
                }
            }

            if ($targetRecord === null) {
                throw new InsufficientPointsException(__('user.insufficient_points', ['points' => $sumPoint]));
            }

            // 最后一条记录有剩余积分，拆分出一条新记录承载剩余部分
            if ($sumPoint > $point) {
                $targetRecord->replicate()->fill([
                    'points' => $sumPoint - $point,
                    'updated_at' => Carbon::now(),
                ])->save();
            }

            // 删除已消耗的积分记录
            PointRecord::query()->whereIn('id', $consumedIds)->delete();

            // 创建积分交易记录（负值表示减少）
            self::createTradeLog($userId, -$point, $source->getKey(), $source->getMorphClass(), $type, $desc);

            return true;
        });
    }

    /**
     * 处理过期积分
     *
     * 回收用户所有已过期的积分
     *
     * @param  int|string  $userId  用户ID
     */
    public static function handlingExpired(int|string $userId): void
    {
        self::connection()->transaction(function () use ($userId): void {
            // 查询所有已过期的积分记录（空过期时间视为永不过期）
            $expiredRecords = PointRecord::query()
                ->where('user_id', $userId)
                ->whereNotNull('expired_at')
                ->where('expired_at', '<=', Carbon::now())
                ->lockForUpdate()
                ->get();

            if ($expiredRecords->isEmpty()) {
                return;
            }

            $totalExpiredPoints = (int) $expiredRecords->sum('points');

            // 删除已过期的积分记录（按已锁定的ID删除，避免与查询条件产生时间漂移）
            PointRecord::query()->whereIn('id', $expiredRecords->modelKeys())->delete();

            // 创建积分交易记录（负值表示减少）
            self::createTradeLog($userId, -$totalExpiredPoints, 0, PointRecord::class, PointType::TYPE_RECOVERY, '过期回收');
        });
    }

    /**
     * 更新用户可用积分总额
     *
     * 重新计算用户当前可用积分并更新到用户表
     *
     * @param  int|string  $userId  用户ID
     */
    public static function updatePointTotal(int|string $userId): void
    {
        // 计算用户当前可用积分总额（未过期的积分）
        $pointTotal = (int) self::availableRecords($userId)->sum('points');

        // 更新用户表中的可用积分字段（该字段为无符号整型，需兜底为非负值）
        User::query()->where('id', $userId)->update(['available_points' => max(0, $pointTotal)]);
    }

    /**
     * 获取用户可用积分记录查询构造器
     *
     * 可用积分为未过期的积分，空过期时间视为永不过期。
     *
     * @param  int|string  $userId  用户ID
     */
    protected static function availableRecords(int|string $userId): Builder
    {
        return PointRecord::query()
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $query
                ->whereNull('expired_at')
                ->orWhere('expired_at', '>', Carbon::now())
            );
    }

    /**
     * 过期时间排序表达式
     *
     * 将空过期时间替换为哨兵值，使其排在最后消耗，同时保证键集分页游标可比较。
     */
    protected static function expireOrderExpression(): string
    {
        return "coalesce(expired_at, '".self::NEVER_EXPIRE_AT."')";
    }

    /**
     * 获取积分相关表所在的数据库连接
     */
    protected static function connection(): \Illuminate\Database\ConnectionInterface
    {
        return PointRecord::query()->getConnection();
    }

    /**
     * 创建积分交易记录
     *
     * @param  int|string  $userId  用户ID
     * @param  int  $points  交易积分数量（正值表示增加，负值表示减少）
     * @param  int|string  $sourceId  关联模型ID
     * @param  string  $sourceType  关联模型类型
     * @param  PointType  $type  交易类型
     * @param  string  $desc  交易描述
     * @param  ?Carbon  $expiredAt  过期时间（可选）
     */
    protected static function createTradeLog(int|string $userId, int $points, int|string $sourceId, string $sourceType, PointType $type, string $desc, ?Carbon $expiredAt = null): PointTrade
    {
        $item = PointTrade::create([
            'user_id' => $userId,
            'points' => $points,
            'source_id' => $sourceId,
            'source_type' => $sourceType,
            'type' => $type,
            'description' => $desc,
            'expired_at' => $expiredAt,
        ]);

        // 更新用户可用积分总额
        static::updatePointTotal($userId);

        return $item;
    }
}
