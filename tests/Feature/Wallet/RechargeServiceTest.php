<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\PayChannel;
use App\Enums\RechargeStatus;
use App\Enums\WalletTradeType;
use App\Events\Wallet\RechargeSucceeded;
use App\Models\Admin\Admin;
use App\Models\System\Setting;
use App\Models\User;
use App\Services\RechargeService;
use Database\Factories\Wallet\RechargeOrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 充值服务测试
 */
#[CoversClass(RechargeService::class)]
#[Group('wallet')]
#[Group('recharge')]
class RechargeServiceTest extends TestCase
{
    use RefreshDatabase;

    private RechargeService $service;

    private User $user;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RechargeService::class);
        $this->user = User::factory()->create();
        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    /**
     * 覆盖系统设置项并清理缓存
     */
    private function setSetting(string $key, mixed $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        app()->forgetInstance('settings');
        \Illuminate\Support\Facades\Cache::flush();
    }

    #[Test]
    #[TestDox('创建订单：生成待支付订单并写入过期时间')]
    public function create_generates_pending_order(): void
    {
        $order = $this->service->create($this->user, 100.50, PayChannel::MANUAL, '127.0.0.1');

        $this->assertSame(RechargeStatus::PENDING, $order->pay_status);
        $this->assertSame('100.50', $order->amount);
        $this->assertSame('user', $order->owner_type);
        $this->assertSame($this->user->id, $order->owner_id);
        $this->assertSame('127.0.0.1', $order->client_ip);
        $this->assertNotNull($order->expired_at);
        $this->assertStringStartsWith('R', $order->order_no);
        // 下单不应立即改变余额
        $this->assertSame('0.00', $this->user->fresh()->balance);
    }

    #[Test]
    #[TestDox('创建订单：订单号唯一')]
    public function order_no_is_unique(): void
    {
        $first = $this->service->create($this->user, 10, PayChannel::MANUAL);
        $second = $this->service->create($this->user, 20, PayChannel::MANUAL);

        $this->assertNotSame($first->order_no, $second->order_no);
    }

    #[Test]
    #[TestDox('创建订单：低于最低金额抛出验证异常')]
    public function create_rejects_amount_below_minimum(): void
    {
        $this->setSetting('wallet.min_recharge_amount', 100);

        $this->expectException(ValidationException::class);
        $this->service->create($this->user, 50, PayChannel::MANUAL);
    }

    #[Test]
    #[TestDox('创建订单：超过最高金额抛出验证异常')]
    public function create_rejects_amount_above_maximum(): void
    {
        $this->setSetting('wallet.max_recharge_amount', 1000);

        $this->expectException(ValidationException::class);
        $this->service->create($this->user, 2000, PayChannel::MANUAL);
    }

    #[Test]
    #[TestDox('创建订单：充值功能关闭时抛出验证异常')]
    public function create_rejects_when_recharge_disabled(): void
    {
        $this->setSetting('wallet.enable_recharge', 0);

        $this->expectException(ValidationException::class);
        $this->service->create($this->user, 100, PayChannel::MANUAL);
    }

    #[Test]
    #[TestDox('创建订单：未接入的支付渠道抛出验证异常')]
    public function create_rejects_unavailable_channel(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create($this->user, 100, PayChannel::WECHAT);
    }

    #[Test]
    #[TestDox('创建订单：按赠送比例计算赠送金额')]
    public function create_calculates_bonus_by_rate(): void
    {
        $this->setSetting('wallet.recharge_bonus_rate', 10);

        $order = $this->service->create($this->user, 200, PayChannel::MANUAL);

        $this->assertSame('20.00', $order->bonus_amount);
        $this->assertSame(220.0, $order->totalAmount());
    }

    #[Test]
    #[TestDox('确认到账：余额入账且生成充值流水')]
    public function confirm_credits_balance_and_creates_trade(): void
    {
        $order = $this->service->create($this->user, 300, PayChannel::MANUAL);

        $confirmed = $this->service->confirmManually($order, $this->admin, '已核对银行流水');

        $this->assertSame(RechargeStatus::SUCCESS, $confirmed->pay_status);
        $this->assertNotNull($confirmed->paid_at);
        $this->assertSame($this->admin->getKey(), $confirmed->confirmed_by);
        $this->assertSame('已核对银行流水', $confirmed->remark);
        $this->assertSame('300.00', $this->user->fresh()->balance);

        $trade = $this->user->walletTrades()->first();
        $this->assertSame(WalletTradeType::RECHARGE, $trade->type);
        $this->assertSame('300.00', $trade->amount);
        $this->assertSame('0.00', $trade->before_balance);
        $this->assertSame('300.00', $trade->after_balance);
        $this->assertSame('recharge_order', $trade->source_type);
        $this->assertSame($order->id, $trade->source_id);
    }

    #[Test]
    #[TestDox('确认到账：赠送金额与本金合并为一笔入账')]
    public function confirm_credits_amount_including_bonus(): void
    {
        $this->setSetting('wallet.recharge_bonus_rate', 20);
        $order = $this->service->create($this->user, 100, PayChannel::MANUAL);

        $this->service->confirmManually($order, $this->admin);

        // 100 本金 + 20 赠送 = 120
        $this->assertSame('120.00', $this->user->fresh()->balance);
        $this->assertSame(1, $this->user->walletTrades()->count());
        $this->assertStringContainsString('含赠送', $this->user->walletTrades()->first()->description);
    }

    #[Test]
    #[TestDox('幂等：重复确认到账只入账一次')]
    public function repeated_confirm_credits_only_once(): void
    {
        $order = $this->service->create($this->user, 150, PayChannel::MANUAL);

        $this->service->confirmManually($order, $this->admin);
        $this->service->confirmManually($order->fresh(), $this->admin);
        $this->service->confirmManually($order->fresh(), $this->admin);

        $this->assertSame('150.00', $this->user->fresh()->balance);
        $this->assertSame(1, $this->user->walletTrades()->count());
    }

    #[Test]
    #[TestDox('确认到账：已过期订单拒绝入账')]
    public function confirm_rejects_expired_order(): void
    {
        $order = RechargeOrderFactory::new()->forUser($this->user)->overdue()->create(['amount' => 100]);

        try {
            $this->service->confirmManually($order, $this->admin);
            $this->fail('已过期订单应拒绝确认');
        } catch (ValidationException) {
            $this->assertSame('0.00', $this->user->fresh()->balance);
            $this->assertSame(0, $this->user->walletTrades()->count());
        }
    }

    #[Test]
    #[TestDox('确认到账：已关闭订单拒绝入账')]
    public function confirm_rejects_closed_order(): void
    {
        $order = $this->service->create($this->user, 100, PayChannel::MANUAL);
        $this->service->close($order, '用户取消');

        try {
            $this->service->confirmManually($order->fresh(), $this->admin);
            $this->fail('已关闭订单应拒绝确认');
        } catch (ValidationException) {
            $this->assertSame('0.00', $this->user->fresh()->balance);
        }
    }

    #[Test]
    #[TestDox('确认到账：派发事件并发送站内信通知')]
    public function confirm_dispatches_event_and_notification(): void
    {
        Event::fake([RechargeSucceeded::class]);
        Notification::fake();

        $order = $this->service->create($this->user, 100, PayChannel::MANUAL);
        $this->service->confirmManually($order, $this->admin);

        Event::assertDispatched(RechargeSucceeded::class);
        Notification::assertSentTo(
            $this->user,
            \App\Notifications\Wallet\RechargeSucceededNotification::class
        );
    }

    #[Test]
    #[TestDox('关闭订单：待支付订单可关闭')]
    public function close_marks_order_as_closed(): void
    {
        $order = $this->service->create($this->user, 100, PayChannel::MANUAL);

        $closed = $this->service->close($order, '用户主动取消');

        $this->assertSame(RechargeStatus::CLOSED, $closed->pay_status);
        $this->assertSame('用户主动取消', $closed->remark);
    }

    #[Test]
    #[TestDox('关闭订单：已到账订单不可关闭')]
    public function close_rejects_paid_order(): void
    {
        $order = $this->service->create($this->user, 100, PayChannel::MANUAL);
        $this->service->confirmManually($order, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->close($order->fresh());
    }

    #[Test]
    #[TestDox('超时关单：仅关闭已超时的待支付订单')]
    public function close_expired_only_targets_overdue_pending_orders(): void
    {
        // 已超时待支付 —— 应被关闭
        $overdue = RechargeOrderFactory::new()->forUser($this->user)->overdue()->create();
        // 未超时待支付 —— 不应被动
        $pending = RechargeOrderFactory::new()->forUser($this->user)->create([
            'expired_at' => Carbon::now()->addMinutes(20),
        ]);
        // 已到账 —— 不应被动
        $paid = RechargeOrderFactory::new()->forUser($this->user)->success()->create([
            'expired_at' => Carbon::now()->subMinutes(5),
        ]);

        $count = $this->service->closeExpired();

        $this->assertSame(1, $count);
        $this->assertSame(RechargeStatus::EXPIRED, $overdue->fresh()->pay_status);
        $this->assertSame(RechargeStatus::PENDING, $pending->fresh()->pay_status);
        $this->assertSame(RechargeStatus::SUCCESS, $paid->fresh()->pay_status);
    }

    #[Test]
    #[TestDox('超时关单：不影响用户余额')]
    public function close_expired_does_not_touch_balance(): void
    {
        RechargeOrderFactory::new()->forUser($this->user)->overdue()->create(['amount' => 500]);

        $this->service->closeExpired();

        $this->assertSame('0.00', $this->user->fresh()->balance);
        $this->assertSame(0, $this->user->walletTrades()->count());
    }
}
