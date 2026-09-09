<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WalletTradeType;
use App\Http\Controllers\Admin\WalletController;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Support\WalletHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台钱包管理功能测试
 */
#[CoversClass(WalletController::class)]
#[Group('admin')]
#[Group('wallet')]
class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->first();
        $this->user = User::factory()->create();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问流水列表返回 401')]
    public function guest_cannot_list_trades(): void
    {
        $this->getJson('/admin/wallet/trades')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录访问钱包统计返回 401')]
    public function guest_cannot_view_stats(): void
    {
        $this->getJson('/admin/wallet/stats')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('流水列表分页返回')]
    public function admin_can_list_trades(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($this->user, 20, $this->user, WalletTradeType::PAYMENT, '消费');

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/trades')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [['id', 'amount', 'before_balance', 'after_balance', 'type', 'status', 'created_at']],
            ]);
    }

    #[Test]
    #[TestDox('流水列表按交易类型筛选')]
    public function admin_can_filter_trades_by_type(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($this->user, 20, $this->user, WalletTradeType::PAYMENT, '消费');

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/trades?type=payment')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type.value', 'payment');
    }

    #[Test]
    #[TestDox('流水列表按持有者筛选')]
    public function admin_can_filter_trades_by_owner(): void
    {
        $other = User::factory()->create();
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::incr($other, 200, $other, WalletTradeType::RECHARGE, '充值');

        $this->actingAsAdmin()
            ->getJson("/admin/wallet/trades?owner_type=user&owner_id={$this->user->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('非法交易类型返回 422')]
    public function invalid_type_filter_returns_422(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/wallet/trades?type=not_exists')
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('流水详情返回完整快照字段')]
    public function admin_can_view_trade_detail(): void
    {
        $trade = WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');

        $this->actingAsAdmin()
            ->getJson("/admin/wallet/trades/{$trade->id}")
            ->assertOk()
            ->assertJsonPath('id', $trade->id)
            ->assertJsonPath('before_balance', '0.00')
            ->assertJsonPath('after_balance', '100.00')
            ->assertJsonPath('before_frozen', '0.00')
            ->assertJsonPath('after_frozen', '0.00');
    }

    #[Test]
    #[TestDox('钱包统计汇总余额与收支')]
    public function admin_can_view_stats(): void
    {
        WalletHelper::incr($this->user, 500, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($this->user, 100, $this->user, WalletTradeType::PAYMENT, '消费');
        WalletHelper::freeze($this->user, 50, $this->user, '冻结');

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/stats')
            ->assertOk()
            ->assertJsonPath('total_balance', 350)
            ->assertJsonPath('total_frozen', 50)
            ->assertJsonPath('total_income', 500)
            ->assertJsonPath('total_expense', 100)
            ->assertJsonPath('trade_count', 3);
    }

    #[Test]
    #[TestDox('指定用户钱包概览')]
    public function admin_can_view_user_wallet(): void
    {
        WalletHelper::incr($this->user, 300, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::freeze($this->user, 100, $this->user, '冻结');

        $this->actingAsAdmin()
            ->getJson("/admin/users/{$this->user->id}/wallet")
            ->assertOk()
            ->assertJsonPath('user_id', $this->user->id)
            ->assertJsonPath('balance', '200.00')
            ->assertJsonPath('frozen_balance', '100.00')
            ->assertJsonPath('available_balance', 100)
            ->assertJsonPath('total_balance', 300);
    }

    #[Test]
    #[TestDox('指定用户钱包流水仅返回该用户数据')]
    public function user_trades_are_scoped_to_the_given_user(): void
    {
        $other = User::factory()->create();
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::incr($other, 200, $other, WalletTradeType::RECHARGE, '充值');

        $this->actingAsAdmin()
            ->getJson("/admin/users/{$this->user->id}/wallet-trades")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', '100.00');
    }

    #[Test]
    #[TestDox('指定用户流水接口无法通过参数越权查询他人数据')]
    public function user_trades_ignores_owner_params(): void
    {
        $other = User::factory()->create();
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::incr($other, 200, $other, WalletTradeType::RECHARGE, '充值');

        // 试图用 owner_id 覆盖路由参数，应被忽略
        $this->actingAsAdmin()
            ->getJson("/admin/users/{$this->user->id}/wallet-trades?owner_id={$other->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', '100.00');
    }

    #[Test]
    #[TestDox('后台充正余额并生成流水')]
    public function admin_can_recharge_user_balance(): void
    {
        $this->actingAsAdmin()
            ->putJson("/admin/users/{$this->user->id}/adjust-wallet", [
                'amount' => 150.25,
                'description' => '活动补偿',
            ])
            ->assertOk()
            ->assertJsonPath('balance', '150.25');

        $this->assertDatabaseHas('wallet_trades', [
            'owner_id' => $this->user->id,
            'type' => 'admin_recharge',
            'description' => '活动补偿',
        ]);
    }

    #[Test]
    #[TestDox('后台扣减余额并生成流水')]
    public function admin_can_deduct_user_balance(): void
    {
        WalletHelper::incr($this->user, 200, $this->user, WalletTradeType::RECHARGE, '充值');

        $this->actingAsAdmin()
            ->putJson("/admin/users/{$this->user->id}/adjust-wallet", [
                'amount' => -80,
                'description' => '违规扣款',
            ])
            ->assertOk()
            ->assertJsonPath('balance', '120.00');

        $this->assertDatabaseHas('wallet_trades', [
            'owner_id' => $this->user->id,
            'type' => 'admin_deduct',
        ]);
    }

    #[Test]
    #[TestDox('扣减金额超过余额返回 422')]
    public function deduct_over_balance_returns_422(): void
    {
        WalletHelper::incr($this->user, 30, $this->user, WalletTradeType::RECHARGE, '充值');

        $this->actingAsAdmin()
            ->putJson("/admin/users/{$this->user->id}/adjust-wallet", [
                'amount' => -100,
                'description' => '超额扣款',
            ])
            ->assertStatus(422);

        $this->assertSame('30.00', $this->user->fresh()->balance);
    }

    #[Test]
    #[TestDox('调账金额为 0 返回 422')]
    public function adjust_with_zero_amount_returns_422(): void
    {
        $this->actingAsAdmin()
            ->putJson("/admin/users/{$this->user->id}/adjust-wallet", [
                'amount' => 0,
                'description' => '零金额',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    #[TestDox('调账缺少说明返回 422')]
    public function adjust_without_description_returns_422(): void
    {
        $this->actingAsAdmin()
            ->putJson("/admin/users/{$this->user->id}/adjust-wallet", [
                'amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }
}
