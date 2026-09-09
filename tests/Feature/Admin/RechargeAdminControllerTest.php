<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PayChannel;
use App\Http\Controllers\Admin\RechargeController;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Services\RechargeService;
use Database\Factories\Wallet\RechargeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台充值订单管理功能测试
 */
#[CoversClass(RechargeController::class)]
#[Group('admin')]
#[Group('wallet')]
#[Group('recharge')]
class RechargeAdminControllerTest extends TestCase
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
    #[TestDox('未登录访问订单列表返回 401')]
    public function guest_cannot_list_orders(): void
    {
        $this->getJson('/admin/wallet/recharges')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录确认到账返回 401')]
    public function guest_cannot_confirm_order(): void
    {
        $order = RechargeFactory::new()->forUser($this->user)->create();

        $this->postJson("/admin/wallet/recharges/{$order->id}/confirm")->assertUnauthorized();
    }

    #[Test]
    #[TestDox('订单列表分页返回')]
    public function admin_can_list_orders(): void
    {
        RechargeFactory::new()->forUser($this->user)->count(3)->create();

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/recharges')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [['id', 'order_no', 'amount', 'bonus_amount', 'total_amount', 'pay_channel', 'pay_status', 'created_at']],
            ]);
    }

    #[Test]
    #[TestDox('订单列表按支付状态筛选')]
    public function admin_can_filter_orders_by_status(): void
    {
        RechargeFactory::new()->forUser($this->user)->count(2)->create();
        RechargeFactory::new()->forUser($this->user)->success()->create();

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/recharges?pay_status=success')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.pay_status.value', 'success');
    }

    #[Test]
    #[TestDox('订单列表按持有者筛选')]
    public function admin_can_filter_orders_by_owner(): void
    {
        $other = User::factory()->create();
        RechargeFactory::new()->forUser($this->user)->create();
        RechargeFactory::new()->forUser($other)->count(2)->create();

        $this->actingAsAdmin()
            ->getJson("/admin/wallet/recharges?owner_type=user&owner_id={$this->user->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('非法支付状态返回 422')]
    public function invalid_status_filter_returns_422(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/wallet/recharges?pay_status=not_exists')
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('订单详情返回回调报文字段')]
    public function admin_can_view_order_detail(): void
    {
        $order = RechargeFactory::new()->forUser($this->user)->create(['amount' => 200]);

        $this->actingAsAdmin()
            ->getJson("/admin/wallet/recharges/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('amount', '200.00')
            ->assertJsonStructure(['callback_payload']);
    }

    #[Test]
    #[TestDox('充值统计汇总金额与成功率')]
    public function admin_can_view_stats(): void
    {
        RechargeFactory::new()->forUser($this->user)->success()->create(['amount' => 300]);
        RechargeFactory::new()->forUser($this->user)->success()->create(['amount' => 200]);
        RechargeFactory::new()->forUser($this->user)->create(['amount' => 100]);

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/recharges/stats')
            ->assertOk()
            ->assertJsonPath('total_amount', 500)
            ->assertJsonPath('total_count', 3)
            ->assertJsonPath('success_count', 2)
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('success_rate', 66.67);
    }

    #[Test]
    #[TestDox('无订单时统计成功率返回 0 而非除零错误')]
    public function stats_returns_zero_rate_without_orders(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/wallet/recharges/stats')
            ->assertOk()
            ->assertJsonPath('total_count', 0)
            ->assertJsonPath('success_rate', 0);
    }

    #[Test]
    #[TestDox('确认到账：订单转为已到账且余额入账')]
    public function admin_can_confirm_order(): void
    {
        $order = app(RechargeService::class)->create($this->user, 250, PayChannel::MANUAL);

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/recharges/{$order->id}/confirm", ['remark' => '已核对'])
            ->assertOk()
            ->assertJsonPath('pay_status.value', 'success')
            ->assertJsonPath('remark', '已核对');

        $this->assertSame('250.00', $this->user->fresh()->balance);
        $this->assertDatabaseHas('wallet_trades', [
            'owner_id' => $this->user->id,
            'type' => 'recharge',
        ]);
    }

    #[Test]
    #[TestDox('确认到账：重复确认不会重复入账')]
    public function repeated_confirm_does_not_double_credit(): void
    {
        $order = app(RechargeService::class)->create($this->user, 100, PayChannel::MANUAL);
        $url = "/admin/wallet/recharges/{$order->id}/confirm";

        $this->actingAsAdmin()->postJson($url)->assertOk();
        $this->actingAsAdmin()->postJson($url)->assertOk();

        $this->assertSame('100.00', $this->user->fresh()->balance);
        $this->assertSame(1, $this->user->walletTrades()->count());
    }

    #[Test]
    #[TestDox('确认到账：已过期订单返回 422')]
    public function confirm_expired_order_returns_422(): void
    {
        $order = RechargeFactory::new()->forUser($this->user)->overdue()->create();

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/recharges/{$order->id}/confirm")
            ->assertStatus(422);

        $this->assertSame('0.00', $this->user->fresh()->balance);
    }

    #[Test]
    #[TestDox('关闭订单：待支付订单可关闭')]
    public function admin_can_close_order(): void
    {
        $order = app(RechargeService::class)->create($this->user, 100, PayChannel::MANUAL);

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/recharges/{$order->id}/close", ['remark' => '用户取消'])
            ->assertOk()
            ->assertJsonPath('pay_status.value', 'closed');
    }

    #[Test]
    #[TestDox('关闭订单：已到账订单返回 422')]
    public function close_paid_order_returns_422(): void
    {
        $order = RechargeFactory::new()->forUser($this->user)->success()->create();

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/recharges/{$order->id}/close")
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('确认备注超长返回 422')]
    public function confirm_with_too_long_remark_returns_422(): void
    {
        $order = RechargeFactory::new()->forUser($this->user)->create();

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/recharges/{$order->id}/confirm", [
                'remark' => str_repeat('a', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remark');
    }
}
