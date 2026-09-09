<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\WalletTradeType;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use App\Models\User;
use App\Support\WalletHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台钱包接口功能测试
 */
#[CoversClass(WalletController::class)]
#[Group('api')]
#[Group('wallet')]
class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    #[TestDox('未登录访问钱包概览返回 401')]
    public function guest_cannot_view_wallet(): void
    {
        $this->getJson('/api/v1/user/wallet')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录访问钱包流水返回 401')]
    public function guest_cannot_view_trades(): void
    {
        $this->getJson('/api/v1/user/wallet/trades')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('钱包概览返回余额与收支汇总')]
    public function user_can_view_wallet_overview(): void
    {
        WalletHelper::incr($this->user, 500, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($this->user, 120, $this->user, WalletTradeType::PAYMENT, '消费');
        WalletHelper::freeze($this->user, 80, $this->user, '下单冻结');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet')
            ->assertOk()
            ->assertJsonPath('balance', '300.00')
            ->assertJsonPath('frozen_balance', '80.00')
            ->assertJsonPath('available_balance', 220)
            ->assertJsonPath('total_balance', 380)
            ->assertJsonPath('total_income', 500)
            ->assertJsonPath('total_expense', 200);
    }

    #[Test]
    #[TestDox('新用户钱包概览余额为零')]
    public function new_user_has_zero_balance(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet')
            ->assertOk()
            ->assertJsonPath('balance', '0.00')
            ->assertJsonPath('frozen_balance', '0.00')
            ->assertJsonPath('total_income', 0)
            ->assertJsonPath('total_expense', 0);
    }

    #[Test]
    #[TestDox('钱包流水分页返回且不暴露持有者信息')]
    public function user_can_list_own_trades(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($this->user, 30, $this->user, WalletTradeType::PAYMENT, '消费');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/trades')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [['id', 'amount', 'type', 'type_label', 'status', 'description', 'created_at']],
            ]);

        // 前台端不应暴露持有者字段
        $this->assertArrayNotHasKey('owner', $response->json('data.0'));
        $this->assertArrayNotHasKey('owner_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('before_balance', $response->json('data.0'));
    }

    #[Test]
    #[TestDox('钱包流水仅返回本人数据')]
    public function trades_only_contain_own_records(): void
    {
        $other = User::factory()->create();
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '我的充值');
        WalletHelper::incr($other, 999, $other, WalletTradeType::RECHARGE, '他人充值');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/trades')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', '我的充值');
    }

    #[Test]
    #[TestDox('钱包流水按交易类型筛选')]
    public function trades_can_be_filtered_by_type(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($this->user, 30, $this->user, WalletTradeType::PAYMENT, '消费');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/trades?type=recharge')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type.value', 'recharge');
    }
}
