<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Operation;

use App\Ai\Tools\Operation\SetBannerStatus;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Operation\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Banner 启用状态变更工具测试
 */
#[CoversClass(SetBannerStatus::class)]
#[Group('ai')]
#[Group('operation')]
class SetBannerStatusTest extends TestCase
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
        $result = (new SetBannerStatus)->handle(new Request(['id' => 1, 'status' => false]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 banners.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['banners.index']);
        $banner = Banner::factory()->create(['status' => true]);

        $result = (new SetBannerStatus)->forAdmin($admin)->handle(new Request([
            'id' => $banner->id,
            'status' => false,
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertTrue($banner->fresh()->status);
    }

    #[Test]
    #[TestDox('禁用 Banner 后状态变更为禁用')]
    public function handle_disables_banner(): void
    {
        $admin = $this->createAdminWithPermissions(['banners.edit']);
        $banner = Banner::factory()->create(['status' => true]);

        $result = (new SetBannerStatus)->forAdmin($admin)->handle(new Request([
            'id' => $banner->id,
            'status' => false,
        ]));

        $this->assertFalse($banner->fresh()->status);
        $this->assertStringContainsString('已禁用', (string) $result);
    }

    #[Test]
    #[TestDox('启用 Banner 后状态变更为启用')]
    public function handle_enables_banner(): void
    {
        $admin = $this->createAdminWithPermissions(['banners.edit']);
        $banner = Banner::factory()->create(['status' => false]);

        $result = (new SetBannerStatus)->forAdmin($admin)->handle(new Request([
            'id' => $banner->id,
            'status' => true,
        ]));

        $this->assertTrue($banner->fresh()->status);
        $this->assertStringContainsString('已启用', (string) $result);
    }

    #[Test]
    #[TestDox('目标状态与当前一致时提示无需变更')]
    public function handle_skips_when_status_unchanged(): void
    {
        $admin = $this->createAdminWithPermissions(['banners.edit']);
        $banner = Banner::factory()->create(['status' => true]);

        $result = (new SetBannerStatus)->forAdmin($admin)->handle(new Request([
            'id' => $banner->id,
            'status' => true,
        ]));

        $this->assertStringContainsString('无需变更', (string) $result);
    }
}
