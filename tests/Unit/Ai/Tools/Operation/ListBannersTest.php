<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Operation;

use App\Ai\Tools\Operation\ListBanners;
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
 * Banner 列表查询工具测试
 */
#[CoversClass(ListBanners::class)]
#[Group('ai')]
#[Group('operation')]
class ListBannersTest extends TestCase
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
        $result = (new ListBanners)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 banners.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListBanners)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页 Banner 数据')]
    public function handle_returns_paginated_banners(): void
    {
        $admin = $this->createAdminWithPermissions(['banners.index']);
        Banner::factory()->count(2)->create();

        $result = (new ListBanners)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('link_type_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('status 与 position 组合筛选仅返回匹配 Banner')]
    public function handle_filters_by_status_and_position(): void
    {
        $admin = $this->createAdminWithPermissions(['banners.index']);
        Banner::factory()->create(['position' => 'home_top', 'status' => true]);
        Banner::factory()->create(['position' => 'home_top', 'status' => false]);
        Banner::factory()->create(['position' => 'sidebar', 'status' => true]);

        $result = (new ListBanners)->forAdmin($admin)->handle(new Request([
            'position' => 'home_top',
            'status' => true,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('home_top', $payload['data'][0]['position']);
        $this->assertTrue($payload['data'][0]['status']);
    }
}
