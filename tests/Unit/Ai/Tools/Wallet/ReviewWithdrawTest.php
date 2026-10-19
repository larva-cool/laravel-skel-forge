<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\ReviewWithdraw;
use App\Enums\AdminStatus;
use App\Enums\WalletTradeType;
use App\Enums\WithdrawStatus;
use App\Events\Wallet\WithdrawApproved;
use App\Events\Wallet\WithdrawRejected;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use App\Support\WalletHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 提现申请审核工具测试
 */
#[CoversClass(ReviewWithdraw::class)]
#[Group('ai')]
#[Group('wallet')]
class ReviewWithdrawTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ReviewWithdraw)->handle(new Request(['id' => 1, 'action' => 'approve']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 wallet.withdraws 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);
        $withdraw = Withdraw::factory()->create();

        $result = (new ReviewWithdraw)->forAdmin($admin)->handle(new Request([
            'id' => $withdraw->id,
            'action' => 'approve',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(WithdrawStatus::PENDING, $withdraw->fresh()->status);
    }

    #[Test]
    #[TestDox('审核通过后状态流转为已通过')]
    public function approve_transitions_status_to_approved(): void
    {
        Event::fake([WithdrawApproved::class]);
        Notification::fake();

        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        $withdraw = Withdraw::factory()->noFee()->create();

        $result = (new ReviewWithdraw)->forAdmin($admin)->handle(new Request([
            'id' => $withdraw->id,
            'action' => 'approve',
        ]));

        $fresh = $withdraw->fresh();
        $this->assertSame(WithdrawStatus::APPROVED, $fresh->status);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertStringContainsString('已通过提现单', (string) $result);
        Event::assertDispatched(WithdrawApproved::class);
    }

    #[Test]
    #[TestDox('审核驳回后状态流转为已驳回并解冻退回余额')]
    public function reject_transitions_status_and_unfreezes_balance(): void
    {
        Event::fake([WithdrawRejected::class]);
        Notification::fake();

        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        $user = User::factory()->create();
        $withdraw = Withdraw::factory()->forUser($user)->noFee()->create(['amount' => 100]);

        // 模拟用户余额与提现冻结
        WalletHelper::incr($user, 500, $user, WalletTradeType::ADMIN_RECHARGE, '测试充值');
        WalletHelper::freeze($user, 100, $withdraw, '提现冻结');

        $result = (new ReviewWithdraw)->forAdmin($admin)->handle(new Request([
            'id' => $withdraw->id,
            'action' => 'reject',
            'reason' => '收款账户信息有误',
        ]));

        $fresh = $withdraw->fresh();
        $this->assertSame(WithdrawStatus::REJECTED, $fresh->status);
        $this->assertSame('收款账户信息有误', $fresh->reject_reason);
        $this->assertSame(500.0, WalletHelper::getBalance($user));
        $this->assertSame(0.0, WalletHelper::getFrozenBalance($user));
        $this->assertStringContainsString('已驳回提现单', (string) $result);
        Event::assertDispatched(WithdrawRejected::class);
    }

    #[Test]
    #[TestDox('驳回时缺少驳回原因操作被取消')]
    public function reject_requires_reason(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        $withdraw = Withdraw::factory()->create();

        $result = (new ReviewWithdraw)->forAdmin($admin)->handle(new Request([
            'id' => $withdraw->id,
            'action' => 'reject',
        ]));

        $this->assertStringContainsString('必须填写驳回原因', (string) $result);
        $this->assertSame(WithdrawStatus::PENDING, $withdraw->fresh()->status);
    }

    #[Test]
    #[TestDox('非待审核状态的申请不可再次审核')]
    public function review_rejects_non_pending_withdraw(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        $withdraw = Withdraw::factory()->rejected()->create();

        $result = (new ReviewWithdraw)->forAdmin($admin)->handle(new Request([
            'id' => $withdraw->id,
            'action' => 'approve',
        ]));

        $this->assertStringContainsString('不可审核', (string) $result);
        $this->assertSame(WithdrawStatus::REJECTED, $withdraw->fresh()->status);
    }

    #[Test]
    #[TestDox('二次确认信息包含单号与金额摘要')]
    public function needs_approval_includes_order_summary(): void
    {
        $withdraw = Withdraw::factory()->noFee()->create(['amount' => 200]);

        $tool = new ReviewWithdraw;
        $method = new \ReflectionMethod($tool, 'needsApproval');
        $approval = $method->invoke($tool, new Request(['id' => $withdraw->id, 'action' => 'approve']));

        $this->assertStringContainsString($withdraw->order_no, (string) $approval->reason);
    }
}
