<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WalletTradeType;
use App\Http\Controllers\Admin\WithdrawController;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\Wallet\WithdrawAccount;
use App\Services\WithdrawService;
use App\Support\WalletHelper;
use Database\Factories\Wallet\WithdrawFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台提现审核控制器测试
 */
#[CoversClass(WithdrawController::class)]
#[Group('admin')]
#[Group('wallet')]
#[Group('withdraw')]
class WithdrawAdminControllerTest extends TestCase
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

    /**
     * 通过服务层创建一个余额充足用户下的待审核提现单
     */
    private function createPendingWithdraw(float $amount = 200): \App\Models\Wallet\Withdraw
    {
        WalletHelper::incr($this->user, max($amount * 2, 1000), $this->user, WalletTradeType::RECHARGE, 'setup');
        $this->user->refresh();

        $account = WithdrawAccount::factory()->forUser($this->user)->create();

        return app(WithdrawService::class)->submit(
            $this->user,
            $amount,
            $account,
            '127.0.0.1'
        );
    }

    #[Test]
    #[TestDox('未登录访问列表返回 401')]
    public function guest_cannot_list_withdraws(): void
    {
        $this->getJson('/admin/wallet/withdraws')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('提现列表分页返回')]
    public function admin_can_list_withdraws(): void
    {
        WithdrawFactory::new()->forUser($this->user)->count(3)->create();

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/withdraws')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [['id', 'order_no', 'amount', 'fee', 'actual_amount', 'account_type', 'status', 'created_at']],
            ]);
    }

    #[Test]
    #[TestDox('提现列表按状态筛选')]
    public function admin_can_filter_by_status(): void
    {
        WithdrawFactory::new()->forUser($this->user)->count(2)->create();
        WithdrawFactory::new()->forUser($this->user)->success()->create();

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/withdraws?status=success')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status.value', 'success');
    }

    #[Test]
    #[TestDox('非法状态返回 422')]
    public function invalid_status_filter_returns_422(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/wallet/withdraws?status=not_exists')
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('提现详情返回完整字段')]
    public function admin_can_view_detail(): void
    {
        $order = WithdrawFactory::new()->forUser($this->user)->create(['amount' => 500]);

        $this->actingAsAdmin()
            ->getJson("/admin/wallet/withdraws/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('amount', number_format((float) $order->amount, 2, '.', ''));
    }

    #[Test]
    #[TestDox('提现统计汇总')]
    public function admin_can_view_stats(): void
    {
        WithdrawFactory::new()->forUser($this->user)->success()->create(['amount' => 300, 'fee' => 3, 'actual_amount' => 297]);
        WithdrawFactory::new()->forUser($this->user)->success()->create(['amount' => 200, 'fee' => 2, 'actual_amount' => 198]);
        WithdrawFactory::new()->forUser($this->user)->create(['amount' => 100]);

        $this->actingAsAdmin()
            ->getJson('/admin/wallet/withdraws/stats')
            ->assertOk()
            ->assertJsonPath('total_amount', 500)
            ->assertJsonPath('total_fee', 5)
            ->assertJsonPath('total_count', 3)
            ->assertJsonPath('success_count', 2)
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('success_rate', 66.67);
    }

    #[Test]
    #[TestDox('审核通过：状态改为 approved')]
    public function admin_can_approve(): void
    {
        $order = $this->createPendingWithdraw(300);

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/approve", ['remark' => '信息无误'])
            ->assertOk()
            ->assertJsonPath('status.value', 'approved')
            ->assertJsonPath('remark', '信息无误');

        // 资金仍冻结
        $this->assertSame('300.00', $this->user->fresh()->frozen_balance);
    }

    #[Test]
    #[TestDox('审核驳回：原因必填，退回冻结余额')]
    public function admin_can_reject_with_reason(): void
    {
        $order = $this->createPendingWithdraw(300);

        // 缺原因 → 422
        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/reject", ['reason' => '账号姓名不符'])
            ->assertOk()
            ->assertJsonPath('status.value', 'rejected')
            ->assertJsonPath('reject_reason', '账号姓名不符');

        $freshUser = $this->user->fresh();
        $this->assertSame('0.00', $freshUser->frozen_balance);
        $this->assertGreaterThanOrEqual(1000.0, (float) $freshUser->balance);
    }

    #[Test]
    #[TestDox('标记已打款：成功状态并结算冻结金额')]
    public function admin_can_transfer(): void
    {
        $order = $this->createPendingWithdraw(400);
        app(WithdrawService::class)->approve($order, $this->admin);

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/transfer", ['remark' => '已打款'])
            ->assertOk()
            ->assertJsonPath('status.value', 'success')
            ->assertJsonPath('remark', '已打款');

        $freshUser = $this->user->fresh();
        $this->assertSame('0.00', $freshUser->frozen_balance);
        // 初始化入账 1000 + 冻结后 600 = 打款后应剩 600
        $this->assertSame('600.00', $freshUser->balance);
    }

    #[Test]
    #[TestDox('未审核通过时直接打款返回 422')]
    public function transfer_without_approve_returns_422(): void
    {
        $order = $this->createPendingWithdraw(200);

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/transfer")
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('标记打款失败：失败原因为必填，退回冻结金额')]
    public function admin_can_mark_fail(): void
    {
        $order = $this->createPendingWithdraw(400);
        app(WithdrawService::class)->approve($order, $this->admin);

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/fail", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAsAdmin()
            ->postJson("/admin/wallet/withdraws/{$order->id}/fail", ['reason' => '账户异常'])
            ->assertOk()
            ->assertJsonPath('status.value', 'failed')
            ->assertJsonPath('fail_reason', '账户异常');

        $freshUser = $this->user->fresh();
        $this->assertSame('0.00', $freshUser->frozen_balance);
        // 解冻后余额回到 1000
        $this->assertSame('1000.00', $freshUser->balance);
    }

    #[Test]
    #[TestDox('非待审核状态重复审核通过返回 422')]
    public function repeated_approve_returns_422(): void
    {
        $order = $this->createPendingWithdraw(200);
        $url = "/admin/wallet/withdraws/{$order->id}/approve";

        $this->actingAsAdmin()->postJson($url)->assertOk();
        $this->actingAsAdmin()->postJson($url)->assertStatus(422);
    }
}
