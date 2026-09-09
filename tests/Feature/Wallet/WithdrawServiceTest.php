<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\WalletTradeType;
use App\Enums\WithdrawAccountType;
use App\Enums\WithdrawStatus;
use App\Events\Wallet\WithdrawApproved;
use App\Events\Wallet\WithdrawFailed;
use App\Events\Wallet\WithdrawRejected;
use App\Events\Wallet\WithdrawTransferred;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Admin\Admin;
use App\Models\System\Setting;
use App\Models\User;
use App\Models\Wallet\WalletTrade;
use App\Services\WithdrawService;
use App\Support\WalletHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 提现服务测试
 */
#[CoversClass(WithdrawService::class)]
#[Group('wallet')]
#[Group('withdraw')]
class WithdrawServiceTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawService $service;

    private User $user;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WithdrawService::class);
        $this->user = User::factory()->create();
        // 通过钱包引擎入账，避免 mass assignment
        WalletHelper::incr($this->user, 10000, $this->user, WalletTradeType::RECHARGE, '测试初始化');
        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    /**
     * 覆盖系统设置项并清理缓存
     */
    private function setSetting(string $key, mixed $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        // 清理 SettingManagerService 的缓存与单例
        $manager = app(\App\Services\SettingManagerService::class);
        if (method_exists($manager, 'flushCache')) {
            $manager->flushCache();
        }
        app()->forgetInstance(\App\Services\SettingManagerService::class);
        \Illuminate\Support\Facades\Cache::flush();
    }

    /**
     * 标准账户信息
     *
     * @return array<string, string>
     */
    private function accountInfo(): array
    {
        return ['account' => 'wxtest123', 'real_name' => '张三'];
    }

    #[Test]
    #[TestDox('提交申请：冻结金额并生成待审核提现单')]
    public function submit_freezes_balance_and_creates_pending_order(): void
    {
        $order = $this->service->submit(
            $this->user,
            200.0,
            WithdrawAccountType::WECHAT,
            $this->accountInfo(),
            '127.0.0.1'
        );

        $this->assertSame(WithdrawStatus::PENDING, $order->status);
        $this->assertSame('200.00', $order->amount);
        $this->assertSame('0.00', $order->fee);
        $this->assertSame('200.00', $order->actual_amount);
        $this->assertSame(WithdrawAccountType::WECHAT, $order->account_type);
        $this->assertSame('user', $order->owner_type);
        $this->assertSame($this->user->id, $order->owner_id);
        $this->assertSame('127.0.0.1', $order->client_ip);
        $this->assertStringStartsWith('W', $order->order_no);

        $freshUser = $this->user->fresh();
        $this->assertSame('9800.00', $freshUser->balance);
        $this->assertSame('200.00', $freshUser->frozen_balance);

        // 冻结流水 source 应关联到 withdraw
        $trade = WalletTrade::query()->where('type', WalletTradeType::FREEZE)->first();
        $this->assertNotNull($trade);
        $this->assertSame('withdraw', $trade->source_type);
        $this->assertSame($order->id, $trade->source_id);
    }

    #[Test]
    #[TestDox('提交申请：按手续费率计算手续费')]
    public function submit_calculates_fee_by_rate(): void
    {
        $this->setSetting('wallet.withdraw_fee_rate', 1); // 1%

        $order = $this->service->submit($this->user, 1000, WithdrawAccountType::ALIPAY, $this->accountInfo());

        $this->assertSame('10.00', $order->fee);
        $this->assertSame('990.00', $order->actual_amount);
    }

    #[Test]
    #[TestDox('提交申请：订单号唯一')]
    public function order_no_is_unique(): void
    {
        $first = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        // 第二笔需先把第一笔处理完（pending 状态下禁止再次提交）
        WalletHelper::unfreeze($this->user, 200, $first, 'test');
        $first->update(['status' => WithdrawStatus::REJECTED]);

        $second = $this->service->submit($this->user->fresh(), 300, WithdrawAccountType::WECHAT, $this->accountInfo());

        $this->assertNotSame($first->order_no, $second->order_no);
    }

    #[Test]
    #[TestDox('提交申请：余额不足抛出验证异常且不生成订单')]
    public function submit_rejects_when_balance_insufficient(): void
    {
        $user = User::factory()->create();
        WalletHelper::incr($user, 50, $user, WalletTradeType::RECHARGE, '测试小额');

        $this->expectException(ValidationException::class);

        try {
            $this->service->submit($user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        } finally {
            $this->assertDatabaseCount('withdraws', 0);
            $this->assertSame('50.00', $user->fresh()->balance);
            $this->assertSame('0.00', $user->fresh()->frozen_balance);
        }
    }

    #[Test]
    #[TestDox('提交申请：低于最低金额抛出异常')]
    public function submit_rejects_below_minimum(): void
    {
        $this->setSetting('wallet.min_withdraw_amount', 500);

        $this->expectException(ValidationException::class);
        $this->service->submit($this->user, 100, WithdrawAccountType::WECHAT, $this->accountInfo());
    }

    #[Test]
    #[TestDox('提交申请：超过最高金额抛出异常')]
    public function submit_rejects_above_maximum(): void
    {
        $this->setSetting('wallet.max_withdraw_amount', 1000);

        $this->expectException(ValidationException::class);
        $this->service->submit($this->user, 2000, WithdrawAccountType::WECHAT, $this->accountInfo());
    }

    #[Test]
    #[TestDox('提交申请：提现关闭时抛出异常')]
    public function submit_rejects_when_withdraw_disabled(): void
    {
        $this->setSetting('wallet.enable_withdraw', 0);

        $this->expectException(ValidationException::class);
        $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
    }

    #[Test]
    #[TestDox('提交申请：存在待审核提现时拒绝再次提交')]
    public function submit_rejects_when_pending_exists(): void
    {
        $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());

        $this->expectException(ValidationException::class);
        $this->service->submit($this->user->fresh(), 100, WithdrawAccountType::WECHAT, $this->accountInfo());
    }

    #[Test]
    #[TestDox('提交申请：超过每日次数上限抛出异常')]
    public function submit_rejects_daily_limit_exceeded(): void
    {
        $this->setSetting('wallet.daily_withdraw_limit', 1);
        // 提交一笔并处理成终态，使其不阻塞下一笔但占每日次数
        $first = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        WalletHelper::settleFrozen($this->user, 200, $first, WalletTradeType::WITHDRAW, 'test');
        $first->update(['status' => WithdrawStatus::SUCCESS]);

        $this->expectException(ValidationException::class);
        $this->service->submit($this->user->fresh(), 100, WithdrawAccountType::WECHAT, $this->accountInfo());
    }

    #[Test]
    #[TestDox('审核通过：状态变更为 APPROVED，资金保持冻结')]
    public function approve_marks_status_and_keeps_frozen(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());

        Event::fake([WithdrawApproved::class]);
        Notification::fake();

        $approved = $this->service->approve($order, $this->admin, '信息无误');

        $this->assertSame(WithdrawStatus::APPROVED, $approved->status);
        $this->assertSame($this->admin->getKey(), $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertSame('信息无误', $approved->remark);

        $freshUser = $this->user->fresh();
        $this->assertSame('9800.00', $freshUser->balance);
        $this->assertSame('200.00', $freshUser->frozen_balance);

        Event::assertDispatched(WithdrawApproved::class);
        Notification::assertSentTo($this->user, \App\Notifications\Wallet\WithdrawApprovedNotification::class);
    }

    #[Test]
    #[TestDox('审核通过：非待审核状态不可审核')]
    public function approve_rejects_non_pending(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        $this->service->approve($order, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->approve($order->fresh(), $this->admin);
    }

    #[Test]
    #[TestDox('审核驳回：解冻退回余额并写入驳回原因')]
    public function reject_unfreezes_and_sets_reason(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());

        Event::fake([WithdrawRejected::class]);
        Notification::fake();

        $rejected = $this->service->reject($order, $this->admin, '账号信息有误', '请核对后重新提交');

        $this->assertSame(WithdrawStatus::REJECTED, $rejected->status);
        $this->assertSame('账号信息有误', $rejected->reject_reason);
        $this->assertSame('请核对后重新提交', $rejected->remark);

        $freshUser = $this->user->fresh();
        $this->assertSame('10000.00', $freshUser->balance);
        $this->assertSame('0.00', $freshUser->frozen_balance);

        // 初始化充值 1 条 + 冻结 1 条 + 解冻 1 条 = 3
        $this->assertSame(3, $this->user->walletTrades()->count());
        $unfreeze = $this->user->walletTrades()->where('type', WalletTradeType::UNFREEZE)->first();
        $this->assertNotNull($unfreeze);
        $this->assertSame('withdraw', $unfreeze->source_type);
        $this->assertSame($order->id, $unfreeze->source_id);

        Event::assertDispatched(WithdrawRejected::class);
    }

    #[Test]
    #[TestDox('审核驳回：驳回原因为必填项')]
    public function reject_requires_reason(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());

        $this->expectException(ValidationException::class);
        $this->service->reject($order, $this->admin, '   ');
    }

    #[Test]
    #[TestDox('打款成功：结算冻结金额，资金流出')]
    public function transfer_settles_frozen_balance(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        $this->service->approve($order, $this->admin);

        Event::fake([WithdrawTransferred::class]);
        Notification::fake();

        $success = $this->service->markTransferred($order->fresh(), $this->admin, '已打款至微信');

        $this->assertSame(WithdrawStatus::SUCCESS, $success->status);
        $this->assertNotNull($success->transfer_at);
        $this->assertSame('已打款至微信', $success->remark);

        $freshUser = $this->user->fresh();
        $this->assertSame('9800.00', $freshUser->balance);
        $this->assertSame('0.00', $freshUser->frozen_balance);

        $withdrawTrade = $this->user->walletTrades()->where('type', WalletTradeType::WITHDRAW)->first();
        $this->assertNotNull($withdrawTrade);
        $this->assertSame('-200.00', $withdrawTrade->amount);

        Event::assertDispatched(WithdrawTransferred::class);
    }

    #[Test]
    #[TestDox('打款成功：仅审核通过状态可执行')]
    public function transfer_rejects_non_approved(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());

        $this->expectException(ValidationException::class);
        $this->service->markTransferred($order, $this->admin);
    }

    #[Test]
    #[TestDox('打款失败：解冻退回余额并写入失败原因')]
    public function fail_unfreezes_and_sets_reason(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        $this->service->approve($order, $this->admin);

        Event::fake([WithdrawFailed::class]);
        Notification::fake();

        $failed = $this->service->markFailed($order->fresh(), $this->admin, '账号实名不一致');

        $this->assertSame(WithdrawStatus::FAILED, $failed->status);
        $this->assertSame('账号实名不一致', $failed->fail_reason);

        $freshUser = $this->user->fresh();
        $this->assertSame('10000.00', $freshUser->balance);
        $this->assertSame('0.00', $freshUser->frozen_balance);

        Event::assertDispatched(WithdrawFailed::class);
    }

    #[Test]
    #[TestDox('打款失败：失败原因为必填项')]
    public function fail_requires_reason(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        $this->service->approve($order, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->markFailed($order->fresh(), $this->admin, '');
    }

    #[Test]
    #[TestDox('幂等：重复审核通过/驳回/打款/失败只触发一次资金变动')]
    public function repeated_status_transitions_are_idempotent(): void
    {
        $order = $this->service->submit($this->user, 200, WithdrawAccountType::WECHAT, $this->accountInfo());
        $this->service->approve($order, $this->admin);

        // 重复 approve 应抛异常，不允许重复操作
        try {
            $this->service->approve($order->fresh(), $this->admin);
            $this->fail('重复 approve 应被拒绝');
        } catch (ValidationException) {
            // expected
        }

        $success = $this->service->markTransferred($order->fresh(), $this->admin);
        try {
            $this->service->markTransferred($success->fresh(), $this->admin);
            $this->fail('重复打款应被拒绝');
        } catch (ValidationException) {
            // expected
        }

        $freshUser = $this->user->fresh();
        $this->assertSame('9800.00', $freshUser->balance);
        $this->assertSame('0.00', $freshUser->frozen_balance);
        // 初始化充值1 + 冻结1 + WITHDRAW 1 = 3
        $this->assertSame(3, $this->user->walletTrades()->count());
    }

    #[Test]
    #[TestDox('calculateFee：按费率计算手续费，费率为 0 时不收手续费')]
    public function calculate_fee_returns_zero_when_rate_disabled(): void
    {
        $this->setSetting('wallet.withdraw_fee_rate', 0);
        $this->assertSame(0.0, $this->service->calculateFee(1000));

        $this->setSetting('wallet.withdraw_fee_rate', 10);
        $this->assertSame(100.0, $this->service->calculateFee(1000));
        $this->assertSame(1.00, $this->service->calculateFee(10));
    }
}
