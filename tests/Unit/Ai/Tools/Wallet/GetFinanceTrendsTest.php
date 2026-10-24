<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\GetFinanceTrends;
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
 * 财务趋势分析工具测试
 *
 * 验证 GetFinanceTrends 的权限兜底、按日聚合与环比计算口径。
 */
#[CoversClass(GetFinanceTrends::class)]
#[Group('ai')]
#[Group('wallet')]
class GetFinanceTrendsTest extends TestCase
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
        $result = (new GetFinanceTrends)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少钱包权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new GetFinanceTrends)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('趋势按日聚合并正确计算环比')]
    public function handle_aggregates_daily_series_and_change_pct(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);
        $user = User::factory()->create();

        // 本期：今天到账 100（含赠送 10）、提现成功 50、净流入 +200
        Recharge::factory()->forUser($user)->success()->withBonus(10)->create(['amount' => 90]);
        Withdraw::factory()->forUser($user)->success()->create(['amount' => 50]);
        WalletTrade::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'amount' => 200,
            'before_balance' => 0,
            'after_balance' => 200,
            'before_frozen' => 0,
            'after_frozen' => 0,
            'type' => WalletTradeType::RECHARGE,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '充值',
        ]);

        // 上期（7 天窗口往前）：10 天前到账 200；冻结流水不计入净流入
        Recharge::factory()->forUser($user)->create([
            'amount' => 200,
            'pay_status' => RechargeStatus::SUCCESS,
            'paid_at' => now()->subDays(10),
        ]);
        $frozen = WalletTrade::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'amount' => -50,
            'before_balance' => 200,
            'after_balance' => 150,
            'before_frozen' => 0,
            'after_frozen' => 50,
            'type' => WalletTradeType::FREEZE,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '冻结',
        ]);
        $frozen->created_at = now()->subDays(10);
        $frozen->saveQuietly();

        $result = (new GetFinanceTrends)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);

        // 本期与上期汇总
        $this->assertSame(100.0, $payload['totals']['current']['recharge_amount']);
        $this->assertSame(50.0, $payload['totals']['current']['withdraw_amount']);
        $this->assertSame(200.0, $payload['totals']['current']['net_flow']);
        $this->assertSame(200.0, $payload['totals']['previous']['recharge_amount']);
        $this->assertSame(0.0, $payload['totals']['previous']['net_flow']);

        // 环比：充值 (100-200)/200 = -50%；提现上期为 0 返回 null
        $this->assertSame(-50.0, $payload['totals']['change_pct']['recharge_amount']);
        $this->assertNull($payload['totals']['change_pct']['withdraw_amount']);

        // 按日序列：7 天，今天包含本期的三笔
        $this->assertCount(7, $payload['daily']);
        $today = collect($payload['daily'])->firstWhere('date', now()->toDateString());
        $this->assertSame(100.0, $today['recharge_amount']);
        $this->assertSame(50.0, $today['withdraw_amount']);
        $this->assertSame(200.0, $today['net_flow']);
    }

    #[Test]
    #[TestDox('无数据时按日序列补零')]
    public function handle_fills_zero_for_empty_days(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);

        $result = (new GetFinanceTrends)->forAdmin($admin)->handle(new Request(['days' => 3]));

        $payload = json_decode((string) $result, true);
        $this->assertCount(3, $payload['daily']);
        foreach ($payload['daily'] as $row) {
            $this->assertSame(0.0, $row['recharge_amount']);
            $this->assertSame(0.0, $row['net_flow']);
        }
        $this->assertSame(0.0, $payload['totals']['current']['recharge_amount']);
    }
}
