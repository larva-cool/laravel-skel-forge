<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\CoinType;
use App\Exceptions\InsufficientCoinsException;
use App\Models\Coin\CoinTrade;
use App\Models\User;
use App\Support\CoinHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * CoinHelper 单元测试
 */
#[CoversClass(CoinHelper::class)]
#[Group('support')]
#[Group('coin')]
class CoinHelperTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('incr 增加金币并同步用户可用金币')]
    public function incr_increases_available_coins(): void
    {
        $user = User::factory()->create();

        $trade = CoinHelper::incr($user, 100, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');

        $this->assertSame(100, $trade->coins);
        $this->assertSame(100, $user->fresh()->available_coins);
    }

    #[Test]
    #[TestDox('incr 传入用户模型时实时刷新余额')]
    public function incr_refreshes_passed_user_model(): void
    {
        $user = User::factory()->create();

        CoinHelper::incr($user, 100, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');

        $this->assertSame(100, $user->available_coins);
    }

    #[Test]
    #[TestDox('decr 扣减金币并实时刷新传入的用户模型')]
    public function decr_refreshes_passed_user_model(): void
    {
        $user = User::factory()->create();
        CoinHelper::incr($user, 100, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');

        CoinHelper::decr($user, 30, $user, CoinType::TYPE_ADMIN_DEDUCT, '后台扣减');

        $this->assertSame(70, $user->available_coins);
        $this->assertSame(70, $user->fresh()->available_coins);
    }

    #[Test]
    #[TestDox('decr 金币不足时抛出异常且不产生流水')]
    public function decr_throws_when_coins_insufficient(): void
    {
        $user = User::factory()->create();
        CoinHelper::incr($user, 10, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');

        $this->expectException(InsufficientCoinsException::class);

        try {
            CoinHelper::decr($user, 50, $user, CoinType::TYPE_ADMIN_DEDUCT, '后台扣减');
        } finally {
            $this->assertSame(10, $user->fresh()->available_coins);
            $this->assertSame(1, CoinTrade::query()->where('user_id', $user->id)->count());
        }
    }

    #[Test]
    #[TestDox('incr 金币数量非正数时抛出异常')]
    public function incr_throws_on_non_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        CoinHelper::incr($user, 0, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');
    }

    #[Test]
    #[TestDox('incr 支持传入用户ID')]
    public function incr_accepts_user_id(): void
    {
        $user = User::factory()->create();

        CoinHelper::incr($user->id, 50, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');

        $this->assertSame(50, $user->fresh()->available_coins);
    }

    #[Test]
    #[TestDox('updateCoinTotal 按流水重新汇总用户可用金币')]
    public function update_coin_total_recalculates_from_trades(): void
    {
        $user = User::factory()->create();
        CoinHelper::incr($user, 100, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');

        // 模拟余额漂移
        $user->updateQuietly(['available_coins' => 999]);

        CoinHelper::updateCoinTotal($user->id);

        $this->assertSame(100, $user->fresh()->available_coins);
    }

    #[Test]
    #[TestDox('用户不存在时抛出异常')]
    public function incr_throws_when_user_not_found(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        CoinHelper::incr(999999, 50, $user, CoinType::TYPE_ADMIN_RECHARGE, '后台充值');
    }
}
