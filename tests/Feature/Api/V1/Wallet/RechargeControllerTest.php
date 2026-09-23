<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1\Wallet;

use App\Enums\PayChannel;
use App\Http\Controllers\Api\V1\Wallet\RechargeController;
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
 * 前台充值接口功能测试
 */
#[CoversClass(RechargeController::class)]
#[Group('api')]
#[Group('wallet')]
#[Group('recharge')]
class RechargeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    #[TestDox('未登录创建订单返回 401')]
    public function guest_cannot_create_order(): void
    {
        $this->postJson('/api/v1/user/wallet/recharges', [
            'amount' => 100,
            'pay_channel' => 'manual',
        ])->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录查看订单列表返回 401')]
    public function guest_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/user/wallet/recharges')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('创建订单：返回 201 与待支付订单')]
    public function user_can_create_order(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/recharges', [
                'amount' => 199.99,
                'pay_channel' => 'manual',
            ])
            ->assertCreated()
            ->assertJsonPath('amount', '199.99')
            ->assertJsonPath('pay_status.value', 'pending')
            ->assertJsonStructure(['id', 'order_no', 'total_amount', 'expired_at']);

        // 下单不应立即改变余额
        $this->assertSame('0.00', $this->user->fresh()->balance);
        $this->assertSame(1, $this->user->recharges()->count());
    }

    #[Test]
    #[TestDox('创建订单：金额为 0 返回 422')]
    public function create_with_zero_amount_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/recharges', [
                'amount' => 0,
                'pay_channel' => 'manual',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    #[TestDox('创建订单：负数金额返回 422')]
    public function create_with_negative_amount_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/recharges', [
                'amount' => -100,
                'pay_channel' => 'manual',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    #[TestDox('创建订单：未接入的支付渠道返回 422')]
    public function create_with_unavailable_channel_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/recharges', [
                'amount' => 100,
                'pay_channel' => 'wechat',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pay_channel');
    }

    #[Test]
    #[TestDox('创建订单：缺少支付渠道返回 422')]
    public function create_without_channel_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/recharges', ['amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pay_channel');
    }

    #[Test]
    #[TestDox('订单列表仅返回本人订单且不暴露内部字段')]
    public function order_list_is_scoped_to_current_user(): void
    {
        $other = User::factory()->create();
        RechargeFactory::new()->forUser($this->user)->create(['amount' => 100]);
        RechargeFactory::new()->forUser($other)->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/recharges')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', '100.00');

        // 前台端不应暴露持有者与下单IP等内部字段
        $this->assertArrayNotHasKey('owner', $response->json('data.0'));
        $this->assertArrayNotHasKey('client_ip', $response->json('data.0'));
        $this->assertArrayNotHasKey('callback_payload', $response->json('data.0'));
        $this->assertArrayNotHasKey('confirmed_by', $response->json('data.0'));
    }

    #[Test]
    #[TestDox('订单列表按支付状态筛选')]
    public function order_list_can_be_filtered_by_status(): void
    {
        RechargeFactory::new()->forUser($this->user)->create();
        RechargeFactory::new()->forUser($this->user)->success()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/recharges?pay_status=success')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.pay_status.value', 'success');
    }

    #[Test]
    #[TestDox('订单详情：可查看本人订单')]
    public function user_can_view_own_order(): void
    {
        $order = app(RechargeService::class)->create($this->user, 150, PayChannel::MANUAL);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/user/wallet/recharges/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('amount', '150.00');
    }

    #[Test]
    #[TestDox('订单详情：越权查看他人订单返回 403')]
    public function user_cannot_view_others_order(): void
    {
        $other = User::factory()->create();
        $order = RechargeFactory::new()->forUser($other)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/user/wallet/recharges/{$order->id}")
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('确认到账后前台可见订单状态与余额同步')]
    public function order_status_reflects_after_confirmation(): void
    {
        $admin = \App\Models\Admin\Admin::query()->where('username', 'admin')->first();
        $order = app(RechargeService::class)->create($this->user, 200, PayChannel::MANUAL);
        app(RechargeService::class)->confirmManually($order, $admin);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/user/wallet/recharges/{$order->id}")
            ->assertOk()
            ->assertJsonPath('pay_status.value', 'success');

        // 重新解析用户，避免使用入账前的陈旧内存实例
        $this->actingAs($this->user->fresh(), 'sanctum')
            ->getJson('/api/v1/user/wallet')
            ->assertOk()
            ->assertJsonPath('balance', '200.00');
    }
}
