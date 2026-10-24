<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Stats;

use App\Ai\Tools\Stats\GetUserGrowthStats;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\User\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户增长趋势分析工具测试
 *
 * 验证 GetUserGrowthStats 的权限兜底、新增环比与留存率推算口径。
 */
#[CoversClass(GetUserGrowthStats::class)]
#[Group('ai')]
#[Group('users')]
class GetUserGrowthStatsTest extends TestCase
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
        $result = (new GetUserGrowthStats)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少用户权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);

        $result = (new GetUserGrowthStats)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('新增环比按本期与上期注册数计算')]
    public function handle_computes_new_user_change_pct(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        User::factory()->count(2)->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()->subDays(10)]);

        $result = (new GetUserGrowthStats)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(3, $payload['summary']['total_users']);
        $this->assertSame(2, $payload['summary']['new_in_range']);
        $this->assertSame(1, $payload['summary']['new_prev_range']);
        $this->assertSame(100.0, $payload['summary']['new_change_pct']);
    }

    #[Test]
    #[TestDox('次日与 7 日留存按注册队列最后活跃时间推算')]
    public function handle_computes_retention_rates(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        // 次日留存：A 命中（3 天前注册、2 天前活跃）；B 未命中（4 天前注册后未再活跃）
        User::factory()->create(['created_at' => now()->subDays(3), 'last_active_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(4), 'last_active_at' => now()->subDays(4)]);

        // 7 日留存：C 命中（8 天前注册、1 天前活跃）；D 未命中（8 天前注册、5 天前活跃）
        User::factory()->create(['created_at' => now()->subDays(8), 'last_active_at' => now()->subDays(1)]);
        User::factory()->create(['created_at' => now()->subDays(8), 'last_active_at' => now()->subDays(5)]);

        $result = (new GetUserGrowthStats)->forAdmin($admin)->handle(new Request(['days' => 30]));

        $payload = json_decode((string) $result, true);

        // 次日队列含全部 4 人，A/C/D 注册满 1 天后仍活跃 → 75%
        $this->assertSame(4, $payload['retention']['day1']['cohort']);
        $this->assertSame(3, $payload['retention']['day1']['retained']);
        $this->assertSame(75.0, $payload['retention']['day1']['rate']);

        // 7 日队列仅 C/D 注册满 7 天，C 命中 → 50%
        $this->assertSame(2, $payload['retention']['day7']['cohort']);
        $this->assertSame(1, $payload['retention']['day7']['retained']);
        $this->assertSame(50.0, $payload['retention']['day7']['rate']);
    }

    #[Test]
    #[TestDox('按日走势来自每日统计快照表')]
    public function handle_reads_daily_trend_from_user_stats(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        UserStat::create([
            'stat_date' => now()->subDay()->toDateString(),
            'total_user_count' => 10,
            'new_user_count' => 3,
            'active_user_count' => 6,
        ]);
        UserStat::create([
            'stat_date' => now()->toDateString(),
            'total_user_count' => 12,
            'new_user_count' => 2,
            'active_user_count' => 8,
        ]);

        $result = (new GetUserGrowthStats)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertCount(2, $payload['daily']);
        $this->assertSame(now()->subDay()->toDateString(), $payload['daily'][0]['date']);
        $this->assertSame(3, $payload['daily'][0]['new']);
        $this->assertSame(8, $payload['daily'][1]['active']);
    }

    #[Test]
    #[TestDox('留存队列为空时比率返回 null')]
    public function handle_returns_null_rate_for_empty_cohort(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new GetUserGrowthStats)->forAdmin($admin)->handle(new Request(['days' => 30]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(0, $payload['retention']['day1']['cohort']);
        $this->assertNull($payload['retention']['day1']['rate']);
        $this->assertSame([], $payload['daily']);
    }
}
