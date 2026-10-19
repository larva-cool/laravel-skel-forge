<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Segments;

use App\Ai\Tools\Segments\SyncUserSegment;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\UserSegment\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 规则分群同步工具测试
 */
#[CoversClass(SyncUserSegment::class)]
#[Group('ai')]
#[Group('segment')]
class SyncUserSegmentTest extends TestCase
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
        $result = (new SyncUserSegment)->handle(new Request(['id' => 1]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 segments.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.index']);
        $segment = Segment::factory()->rule()->create();

        $result = (new SyncUserSegment)->forAdmin($admin)->handle(new Request(['id' => $segment->id]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertNull($segment->fresh()->last_synced_at);
    }

    #[Test]
    #[TestDox('同步规则分群后写回同步时间与匹配用户数')]
    public function handle_syncs_rule_segment(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.edit']);
        User::factory()->count(2)->create(['status' => \App\Enums\UserStatus::ACTIVE]);
        $segment = Segment::factory()->rule()->create();

        $result = (new SyncUserSegment)->forAdmin($admin)->handle(new Request(['id' => $segment->id]));

        $fresh = $segment->fresh();
        $this->assertNotNull($fresh->last_synced_at);
        $this->assertNotNull($fresh->last_synced_user_count);
        $this->assertStringContainsString('同步完成', (string) $result);
    }

    #[Test]
    #[TestDox('手动分群不支持规则同步')]
    public function handle_rejects_manual_segment(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.edit']);
        $segment = Segment::factory()->manual()->create();

        $result = (new SyncUserSegment)->forAdmin($admin)->handle(new Request(['id' => $segment->id]));

        $this->assertStringContainsString('仅规则分群支持同步', (string) $result);
    }

    #[Test]
    #[TestDox('禁用的分群不允许同步')]
    public function handle_rejects_disabled_segment(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.edit']);
        $segment = Segment::factory()->rule()->disabled()->create();

        $result = (new SyncUserSegment)->forAdmin($admin)->handle(new Request(['id' => $segment->id]));

        $this->assertStringContainsString('禁用的分群不允许同步', (string) $result);
    }
}
