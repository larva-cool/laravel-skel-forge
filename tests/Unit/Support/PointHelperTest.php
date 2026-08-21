<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\PointType;
use App\Exceptions\InsufficientPointsException;
use App\Models\Point\PointRecord;
use App\Models\Point\PointTrade;
use App\Models\User;
use App\Support\PointHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * PointHelper 单元测试
 */
#[CoversClass(PointHelper::class)]
#[Group('support')]
#[Group('point')]
class PointHelperTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('incr 增加积分并同步用户可用积分')]
    public function incr_increases_available_points(): void
    {
        $user = User::factory()->create();

        $trade = PointHelper::incr($user->id, 100, $user, PointType::TYPE_ADMIN_RECHARGE, '后台充值');

        $this->assertSame(100, $trade->points);
        $this->assertSame(100, $user->fresh()->available_points);
        $this->assertSame(100, (int) PointRecord::query()->where('user_id', $user->id)->sum('points'));
    }

    #[Test]
    #[TestDox('incr 积分数量非正数时抛出异常')]
    public function incr_throws_on_non_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        try {
            PointHelper::incr($user->id, -50, $user, PointType::TYPE_ADMIN_RECHARGE, '后台充值');
        } finally {
            $this->assertSame(0, $user->fresh()->available_points);
            $this->assertSame(0, PointTrade::query()->where('user_id', $user->id)->count());
        }
    }

    #[Test]
    #[TestDox('decr 扣减积分并同步用户可用积分')]
    public function decr_decreases_available_points(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 100, $user, PointType::TYPE_ADMIN_RECHARGE, '后台充值');

        PointHelper::decr($user->id, 30, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');

        $this->assertSame(70, $user->fresh()->available_points);
        $this->assertSame(70, (int) PointRecord::query()->where('user_id', $user->id)->sum('points'));
    }

    #[Test]
    #[TestDox('decr 恰好扣完时清空可用积分记录')]
    public function decr_consumes_all_records_when_amount_matches(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 60, $user, PointType::TYPE_ADMIN_RECHARGE, '充值一');
        PointHelper::incr($user->id, 40, $user, PointType::TYPE_ADMIN_RECHARGE, '充值二');

        PointHelper::decr($user->id, 100, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');

        $this->assertSame(0, $user->fresh()->available_points);
        $this->assertSame(0, PointRecord::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    #[TestDox('decr 积分数量非正数时抛出异常')]
    public function decr_throws_on_non_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        PointHelper::decr($user->id, 0, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');
    }

    #[Test]
    #[TestDox('decr 积分不足时抛出异常且不产生流水与记录变更')]
    public function decr_throws_when_points_insufficient(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 10, $user, PointType::TYPE_ADMIN_RECHARGE, '后台充值');

        $this->expectException(InsufficientPointsException::class);

        try {
            PointHelper::decr($user->id, 50, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');
        } finally {
            $this->assertSame(10, $user->fresh()->available_points);
            $this->assertSame(1, PointTrade::query()->where('user_id', $user->id)->count());
            $this->assertSame(1, PointRecord::query()->where('user_id', $user->id)->count());
        }
    }

    #[Test]
    #[TestDox('decr 遵循先过期先使用原则')]
    public function decr_consumes_earliest_expiring_records_first(): void
    {
        $user = User::factory()->create();
        $late = $this->createRecord($user, 50, Carbon::now()->addDays(30));
        $early = $this->createRecord($user, 50, Carbon::now()->addDays(1));
        PointHelper::updatePointTotal($user->id);

        PointHelper::decr($user->id, 50, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');

        $this->assertSame(50, $user->fresh()->available_points);
        $this->assertNull(PointRecord::query()->find($early->id));
        $this->assertNotNull(PointRecord::query()->find($late->id));
    }

    #[Test]
    #[TestDox('decr 跨多批次遍历时积分总数保持准确')]
    public function decr_keeps_total_accurate_across_multiple_chunks(): void
    {
        $user = User::factory()->create();

        // 构造 120 条记录，且过期时间顺序与主键顺序完全相反，
        // 以覆盖「翻页游标与排序键不一致」导致的重复累加或漏读。
        for ($i = 0; $i < 120; $i++) {
            $this->createRecord($user, 1, Carbon::now()->addDays(120 - $i));
        }
        PointHelper::updatePointTotal($user->id);
        $this->assertSame(120, $user->fresh()->available_points);

        PointHelper::decr($user->id, 115, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');

        $this->assertSame(5, $user->fresh()->available_points);
        $this->assertSame(5, (int) PointRecord::query()->where('user_id', $user->id)->sum('points'));
    }

    #[Test]
    #[TestDox('过期时间为空的积分视为永不过期并计入可用积分')]
    public function records_without_expiration_are_available(): void
    {
        $user = User::factory()->create();
        $this->createRecord($user, 80, null);

        PointHelper::updatePointTotal($user->id);

        $this->assertSame(80, $user->fresh()->available_points);

        PointHelper::decr($user->id, 30, $user, PointType::TYPE_ADMIN_DEDUCT, '后台扣减');

        $this->assertSame(50, $user->fresh()->available_points);
    }

    #[Test]
    #[TestDox('已过期的积分不计入可用积分')]
    public function expired_records_are_excluded_from_total(): void
    {
        $user = User::factory()->create();
        $this->createRecord($user, 40, Carbon::now()->subDay());
        $this->createRecord($user, 60, Carbon::now()->addDay());

        PointHelper::updatePointTotal($user->id);

        $this->assertSame(60, $user->fresh()->available_points);
    }

    #[Test]
    #[TestDox('handlingExpired 回收过期积分并写入回收流水')]
    public function handling_expired_recycles_expired_points(): void
    {
        $user = User::factory()->create();
        $this->createRecord($user, 40, Carbon::now()->subDay());
        $this->createRecord($user, 60, Carbon::now()->addDay());
        PointHelper::updatePointTotal($user->id);

        PointHelper::handlingExpired($user->id);

        $this->assertSame(60, $user->fresh()->available_points);
        $this->assertSame(1, PointRecord::query()->where('user_id', $user->id)->count());

        $trade = PointTrade::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertSame(-40, $trade->points);
        $this->assertSame(PointType::TYPE_RECOVERY, $trade->type);
    }

    #[Test]
    #[TestDox('updatePointTotal 按可用积分记录重新汇总')]
    public function update_point_total_recalculates_from_records(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 100, $user, PointType::TYPE_ADMIN_RECHARGE, '后台充值');

        // 模拟余额漂移
        $user->updateQuietly(['available_points' => 999]);

        PointHelper::updatePointTotal($user->id);

        $this->assertSame(100, $user->fresh()->available_points);
    }

    /**
     * 创建一条可用积分记录
     */
    private function createRecord(User $user, int $points, ?Carbon $expiredAt): PointRecord
    {
        return PointRecord::create([
            'user_id' => $user->id,
            'points' => $points,
            'description' => '测试积分',
            'expired_at' => $expiredAt,
        ]);
    }
}
