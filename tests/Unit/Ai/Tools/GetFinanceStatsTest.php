<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\Wallet\GetFinanceStats;
use App\Enums\AdminStatus;
use App\Enums\RechargeStatus;
use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\Wallet\Recharge;
use App\Models\Wallet\WalletTrade;
use App\Models\Wallet\Withdraw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 财务数据概览工具测试
 *
 * 验证 GetFinanceStats 的权限兜底与充值/提现/流水/存量统计口径。
 */
#[CoversClass(GetFinanceStats::class)]
#[Group('ai')]
#[Group('wallet')]
class GetFinanceStatsTest extends TestCase
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
        $result = (new GetFinanceStats)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少钱包权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new GetFinanceStats)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('任一钱包权限点满足即可放行')]
    public function array_permission_allows_any_wallet_match(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);

        $result = (new GetFinanceStats)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringNotContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('财务统计正确汇总充值、提现、流水与存量数据')]
    public function handle_aggregates_finance_stats(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);
        $user = User::factory()->create(['available_coins' => 100, 'frozen_balance' => 50]);

        // 充值：1 笔已到账（含赠送）、1 笔待支付
        Recharge::factory()->forUser($user)->success()->withBonus(10)->create(['amount' => 100]);
        Recharge::factory()->forUser($user)->create(['amount' => 200]);

        // 提现：1 笔待审核、1 笔打款成功
        Withdraw::factory()->forUser($user)->create(['amount' => 300]);
        Withdraw::factory()->forUser($user)->success()->create(['amount' => 500, 'fee' => 0, 'actual_amount' => 500]);

        // 流水：充值入账 100、消费出账 30
        WalletTrade::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'amount' => 100,
            'before_balance' => 0,
            'after_balance' => 100,
            'before_frozen' => 0,
            'after_frozen' => 0,
            'type' => WalletTradeType::RECHARGE,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '充值',
        ]);
        WalletTrade::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'amount' => -30,
            'before_balance' => 100,
            'after_balance' => 70,
            'before_frozen' => 0,
            'after_frozen' => 0,
            'type' => WalletTradeType::PAYMENT,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '消费',
        ]);

        $result = (new GetFinanceStats)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);

        // 充值统计
        $this->assertSame(1, $payload['recharges']['success_count']);
        $this->assertSame(100.0, $payload['recharges']['success_amount']);
        $this->assertSame(10.0, $payload['recharges']['bonus_amount']);
        $this->assertSame(110.0, $payload['recharges']['total_credited']);
        $this->assertSame(1, $payload['recharges']['pending_count']);
        $this->assertArrayHasKey('manual', $payload['recharges']['by_channel']);

        // 提现统计
        $this->assertSame(1, $payload['withdraws']['pending_count']);
        $this->assertSame(300.0, $payload['withdraws']['pending_amount']);
        $this->assertSame(1, $payload['withdraws']['success_count']);
        $this->assertSame(500.0, $payload['withdraws']['success_actual_amount']);

        // 流水统计：收入 100，支出 -30，净流入 70
        $this->assertSame(100.0, $payload['wallet_trades']['income']);
        $this->assertSame(-30.0, $payload['wallet_trades']['expense']);
        $this->assertSame(70.0, $payload['wallet_trades']['net']);
        $this->assertArrayHasKey(WalletTradeType::RECHARGE->value, $payload['wallet_trades']['by_type']);

        // 存量统计
        $this->assertSame(100, $payload['stock']['total_available_coins']);
        $this->assertSame(50.0, $payload['stock']['total_frozen_balance']);
    }

    #[Test]
    #[TestDox('超出统计范围的数据不计入区间汇总')]
    public function handle_excludes_records_out_of_range(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);
        $user = User::factory()->create();

        // 30 天前到账的充值，默认 7 天范围内不应统计
        Recharge::factory()->forUser($user)->create([
            'amount' => 999,
            'pay_status' => RechargeStatus::SUCCESS,
            'paid_at' => now()->subDays(30),
        ]);

        $result = (new GetFinanceStats)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(0, $payload['recharges']['success_count']);
        $this->assertSame(0.0, $payload['recharges']['success_amount']);
    }
}
