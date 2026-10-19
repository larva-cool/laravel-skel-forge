<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\Finance;
use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Users\GetUser;
use App\Ai\Tools\Wallet\GetFinanceStats;
use App\Ai\Tools\Wallet\ListRecharges;
use App\Ai\Tools\Wallet\ListWalletTrades;
use App\Ai\Tools\Wallet\ListWithdraws;
use App\Ai\Tools\Wallet\ReviewWithdraw;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Finance 工具权限过滤测试
 *
 * 验证 tools() 按当前对话管理员的权限点过滤工具：
 * 无权限的工具不暴露给模型，并向工具注入管理员用于执行兜底校验。
 */
#[CoversClass(Finance::class)]
#[Group('ai')]
#[Group('permission')]
class FinanceToolPermissionsTest extends TestCase
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

    /**
     * 获取指定管理员可见的工具类名列表
     *
     * @return array<int, string>
     */
    private function toolClasses(Admin $admin): array
    {
        $tools = Finance::make()->forUser($admin)->tools();

        return array_values(array_map(static fn ($tool): string => $tool::class, is_array($tools) ? $tools : iterator_to_array($tools)));
    }

    #[Test]
    #[TestDox('无对话参与者时所有受权限管控的工具均被过滤')]
    public function tools_are_deny_by_default_without_participant(): void
    {
        $tools = Finance::make()->tools();

        $this->assertSame([], is_array($tools) ? $tools : iterator_to_array($tools));
    }

    #[Test]
    #[TestDox('非 Admin 参与者无法获得任何受权限管控的工具')]
    public function tools_are_filtered_for_non_admin_participant(): void
    {
        $user = User::factory()->create();

        $tools = Finance::make()->forUser($user)->tools();

        $this->assertSame([], is_array($tools) ? $tools : iterator_to_array($tools));
    }

    #[Test]
    #[TestDox('超级管理员可获得全部工具')]
    public function super_admin_gets_all_tools(): void
    {
        $superAdmin = Admin::query()->where('username', 'admin')->firstOrFail();

        $this->assertCount(6, $this->toolClasses($superAdmin));
    }

    #[Test]
    #[TestDox('仅拥有 wallet.index 时只能看到流水查询与财务概览工具')]
    public function admin_with_wallet_index_only_sees_trade_read_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ListWalletTrades::class, $classes);
        $this->assertContains(GetFinanceStats::class, $classes);
        $this->assertNotContains(ListRecharges::class, $classes);
        $this->assertNotContains(ListWithdraws::class, $classes);
        $this->assertNotContains(ReviewWithdraw::class, $classes);
        $this->assertCount(2, $classes);
    }

    #[Test]
    #[TestDox('仅拥有 wallet.orders 时只能看到充值订单与财务概览工具')]
    public function admin_with_wallet_orders_only_sees_recharge_read_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ListRecharges::class, $classes);
        $this->assertContains(GetFinanceStats::class, $classes);
        $this->assertNotContains(ListWalletTrades::class, $classes);
        $this->assertCount(2, $classes);
    }

    #[Test]
    #[TestDox('拥有 wallet.withdraws 时同时获得提现查询与审核工具')]
    public function admin_with_wallet_withdraws_sees_review_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ListWithdraws::class, $classes);
        $this->assertContains(ReviewWithdraw::class, $classes);
        $this->assertContains(GetFinanceStats::class, $classes);
        $this->assertCount(3, $classes);
    }

    #[Test]
    #[TestDox('拥有 users.index 时获得用户身份核对工具')]
    public function admin_with_users_index_sees_get_user_tool(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(GetUser::class, $classes);
        $this->assertNotContains(GetFinanceStats::class, $classes);
        $this->assertCount(1, $classes);
    }

    #[Test]
    #[TestDox('可见工具已被注入当前管理员用于执行兜底校验')]
    public function visible_tools_carry_current_admin(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index', 'wallet.orders', 'wallet.withdraws']);

        $tools = Finance::make()->forUser($admin)->tools();
        $tools = is_array($tools) ? $tools : iterator_to_array($tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(RequiresPermission::class, $tool);
            $property = new \ReflectionProperty($tool, 'admin');
            $this->assertSame($admin->id, $property->getValue($tool)?->id);
        }

        // 未通过装配直接 new 的工具未注入管理员
        $fresh = new GetFinanceStats;
        $this->assertNull((new \ReflectionProperty($fresh, 'admin'))->getValue($fresh));
    }
}
