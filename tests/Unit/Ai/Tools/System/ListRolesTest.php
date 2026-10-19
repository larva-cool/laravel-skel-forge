<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\ListRoles;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 后台角色列表查询工具测试
 */
#[CoversClass(ListRoles::class)]
#[Group('ai')]
#[Group('role')]
class ListRolesTest extends TestCase
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
        $result = (new ListRoles)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 roles.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListRoles)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回角色及权限点列表')]
    public function handle_returns_roles_with_permissions(): void
    {
        $admin = $this->createAdminWithPermissions(['roles.index']);
        Role::create([
            'name' => 'unit_test_ops',
            'display_name' => '单元测试运营',
            'guard_name' => AdminMenu::GUARD_NAME,
        ])->syncPermissions(['users.index', 'users.edit']);

        $result = (new ListRoles)->forAdmin($admin)->handle(new Request(['keyword' => 'unit_test_ops']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('unit_test_ops', $payload['data'][0]['name']);
        $this->assertSame(2, $payload['data'][0]['permissions_count']);
        $this->assertContains('users.index', $payload['data'][0]['permissions']);
    }

    #[Test]
    #[TestDox('非 admin 守卫的角色不在返回范围内')]
    public function handle_excludes_non_admin_guard(): void
    {
        $admin = $this->createAdminWithPermissions(['roles.index']);
        Role::create([
            'name' => 'unit_test_api_role',
            'display_name' => '前台角色',
            'guard_name' => 'web',
        ]);

        $result = (new ListRoles)->forAdmin($admin)->handle(new Request(['keyword' => 'unit_test_api_role']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(0, $payload['pagination']['total']);
    }
}
