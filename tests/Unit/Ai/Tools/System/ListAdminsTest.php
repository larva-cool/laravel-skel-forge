<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\ListAdmins;
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
 * 后台管理员列表查询工具测试
 */
#[CoversClass(ListAdmins::class)]
#[Group('ai')]
#[Group('admin')]
class ListAdminsTest extends TestCase
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
        $result = (new ListAdmins)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 admins.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListAdmins)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回管理员摘要与角色')]
    public function handle_returns_paginated_admins_with_roles(): void
    {
        $admin = $this->createAdminWithPermissions(['admins.index']);

        $result = (new ListAdmins)->forAdmin($admin)->handle(new Request(['keyword' => $admin->username]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($admin->username, $payload['data'][0]['username']);
        $this->assertSame($admin->roles->pluck('name')->all(), $payload['data'][0]['roles']);
    }

    #[Test]
    #[TestDox('status 筛选仅返回正常状态的管理员')]
    public function handle_filters_by_status(): void
    {
        $admin = $this->createAdminWithPermissions(['admins.index']);
        Admin::query()->create([
            'username' => 'disabled_unit_test',
            'name' => '禁用管理员',
            'email' => 'disabled_unit_test@example.com',
            'password' => 'password',
            'status' => AdminStatus::DISABLED,
        ]);

        $result = (new ListAdmins)->forAdmin($admin)->handle(new Request([
            'keyword' => 'disabled_unit_test',
            'status' => 1,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(0, $payload['pagination']['total']);
    }
}
