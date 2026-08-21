<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\RoleController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 后台角色管理控制器功能测试
 */
#[CoversClass(RoleController::class)]
#[Group('admin')]
#[Group('role')]
class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    /**
     * 以管理员身份发起请求（actingAs 模拟认证）
     */
    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问角色列表返回 401')]
    public function guest_cannot_list_roles(): void
    {
        $response = $this->getJson('/admin/roles');
        $response->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取角色列表返回 200 与分页数据')]
    public function admin_can_list_roles(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/roles');
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按角色名称搜索角色')]
    public function admin_can_search_roles_by_name(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/roles?role_name=super');
        $response->assertOk()
            ->assertJsonPath('data.0.name', 'super_admin');
    }

    #[Test]
    #[TestDox('创建角色返回 201')]
    public function admin_can_create_role(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/roles', [
            'name' => 'editor',
            'display_name' => 'Editor Role',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'editor');

        $this->assertDatabaseHas('roles', [
            'name' => 'editor',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);
    }

    #[Test]
    #[TestDox('创建角色时名称必填返回 422')]
    public function create_role_requires_name(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/roles', []);
        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('创建角色时名称不能重复返回 422')]
    public function create_role_name_must_be_unique(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => AdminMenu::GUARD_NAME]);

        $response = $this->actingAsAdmin()->postJson('/admin/roles', [
            'name' => 'editor',
        ]);
        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('获取角色详情')]
    public function admin_can_view_role(): void
    {
        $role = Role::where('name', 'super_admin')->where('guard_name', AdminMenu::GUARD_NAME)->first();

        $response = $this->actingAsAdmin()->getJson("/admin/roles/{$role->id}");
        $response->assertOk()
            ->assertJsonPath('id', $role->id)
            ->assertJsonPath('name', 'super_admin');
    }

    #[Test]
    #[TestDox('更新角色成功')]
    public function admin_can_update_role(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => AdminMenu::GUARD_NAME]);

        $response = $this->actingAsAdmin()->putJson("/admin/roles/{$role->id}", [
            'name' => 'auditor',
            'display_name' => 'Auditor Role',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'auditor');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'auditor',
        ]);
    }

    #[Test]
    #[TestDox('超级管理员角色不可修改返回 403')]
    public function super_admin_role_cannot_be_modified(): void
    {
        $role = Role::where('name', 'super_admin')->first();

        $response = $this->actingAsAdmin()->putJson("/admin/roles/{$role->id}", [
            'name' => 'hacker',
            'display_name' => 'Hacker Role',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    #[TestDox('删除角色返回 204')]
    public function admin_can_delete_role(): void
    {
        $role = Role::create(['name' => 'temp', 'guard_name' => AdminMenu::GUARD_NAME]);

        $response = $this->actingAsAdmin()->deleteJson("/admin/roles/{$role->id}");
        $response->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    #[Test]
    #[TestDox('超级管理员角色不可删除返回 403')]
    public function super_admin_role_cannot_be_deleted(): void
    {
        $role = Role::where('name', 'super_admin')->first();

        $response = $this->actingAsAdmin()->deleteJson("/admin/roles/{$role->id}");
        $response->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    #[Test]
    #[TestDox('被使用的角色不可删除返回 400')]
    public function role_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'ops', 'guard_name' => AdminMenu::GUARD_NAME]);
        $this->admin->assignRole($role);

        $response = $this->actingAsAdmin()->deleteJson("/admin/roles/{$role->id}");
        $response->assertBadRequest();
    }

    #[Test]
    #[TestDox('获取角色权限返回 ID 列表')]
    public function can_get_role_permissions(): void
    {
        $role = Role::where('name', 'super_admin')->first();

        $response = $this->actingAsAdmin()->getJson("/admin/roles/{$role->id}/permissions");
        $response->assertOk();
    }

    #[Test]
    #[TestDox('分配角色权限')]
    public function can_assign_permissions_to_role(): void
    {
        $role = Role::create(['name' => 'manager', 'guard_name' => AdminMenu::GUARD_NAME]);

        $permIds = Permission::where('guard_name', AdminMenu::GUARD_NAME)
            ->limit(2)
            ->pluck('id')
            ->toArray();

        $response = $this->actingAsAdmin()->putJson("/admin/roles/{$role->id}/permissions", [
            'permissions' => $permIds,
        ]);

        $response->assertOk();
        $this->assertCount(count($permIds), $role->fresh()->permissions);
    }

    #[Test]
    #[TestDox('获取所有权限列表')]
    public function can_list_all_permissions(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/roles/permissions');
        $response->assertOk();
    }
}
