<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Users;

use App\Ai\Tools\Users\GetChurnRiskUsers;
use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 流失风险用户查询工具测试
 *
 * 验证 GetChurnRiskUsers 的权限兜底、筛选口径与资产沉淀汇总。
 */
#[CoversClass(GetChurnRiskUsers::class)]
#[Group('ai')]
#[Group('users')]
class GetChurnRiskUsersTest extends TestCase
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
        $result = (new GetChurnRiskUsers)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少用户权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);

        $result = (new GetChurnRiskUsers)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('按沉默天数与历史活跃度筛选流失风险用户')]
    public function filters_inactive_but_previously_active_users(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        // 命中：10 天未活跃、累计登录 10 次
        $atRisk = User::factory()->create([
            'last_active_at' => now()->subDays(10),
            'login_count' => 10,
        ]);
        // 命中：从未活跃但注册已满 30 天、累计登录 8 次（注册后活跃过）
        $neverRecent = User::factory()->create([
            'created_at' => now()->subDays(30),
            'last_active_at' => null,
            'login_count' => 8,
        ]);
        // 不命中：1 天前仍活跃
        User::factory()->create(['last_active_at' => now()->subDay(), 'login_count' => 10]);
        // 不命中：累计登录不足阈值
        User::factory()->create(['last_active_at' => now()->subDays(10), 'login_count' => 2]);
        // 不命中：账号已冻结
        User::factory()->create([
            'status' => UserStatus::FROZEN,
            'last_active_at' => now()->subDays(10),
            'login_count' => 10,
        ]);
        // 不命中：注册 2 天的新用户不算沉默
        User::factory()->create([
            'created_at' => now()->subDays(2),
            'last_active_at' => null,
            'login_count' => 6,
        ]);

        $result = (new GetChurnRiskUsers)->forAdmin($admin)->handle(new Request([
            'inactive_days' => 7,
            'min_login_count' => 5,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(2, $payload['summary']['total_at_risk']);
        $this->assertSame(2, $payload['pagination']['total']);

        // 从未活跃（last_active_at 为 null）的排在最前
        $this->assertSame($neverRecent->id, $payload['data'][0]['id']);
        $this->assertNull($payload['data'][0]['inactive_days']);
        $this->assertSame($atRisk->id, $payload['data'][1]['id']);
        $this->assertSame(10, $payload['data'][1]['inactive_days']);
    }

    #[Test]
    #[TestDox('资产沉淀汇总与仅看有资产用户筛选')]
    public function summarizes_sunk_assets_and_filters_by_assets(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $withCoins = User::factory()->create([
            'last_active_at' => now()->subDays(10),
            'login_count' => 10,
            'available_coins' => 100,
        ]);
        $withBalance = User::factory()->create([
            'last_active_at' => now()->subDays(10),
            'login_count' => 10,
            'balance' => 66.5,
        ]);
        User::factory()->create([
            'last_active_at' => now()->subDays(10),
            'login_count' => 10,
            'available_coins' => 0,
            'balance' => 0,
        ]);

        $result = (new GetChurnRiskUsers)->forAdmin($admin)->handle(new Request(['inactive_days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(3, $payload['summary']['total_at_risk']);
        $this->assertSame(2, $payload['summary']['with_assets_count']);
        $this->assertSame(100, $payload['summary']['sunk_coins']);
        $this->assertSame(66.5, $payload['summary']['sunk_balance']);

        // 仅看有资产用户
        $result = (new GetChurnRiskUsers)->forAdmin($admin)->handle(new Request([
            'inactive_days' => 7,
            'with_assets' => true,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(2, $payload['pagination']['total']);
        $ids = array_column($payload['data'], 'id');
        $this->assertContains($withCoins->id, $ids);
        $this->assertContains($withBalance->id, $ids);
    }
}
