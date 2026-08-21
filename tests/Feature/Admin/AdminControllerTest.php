<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 后台管理员管理控制器功能测试
 */
#[CoversClass(AdminController::class)]
#[Group('admin')]
#[Group('admin-user')]
class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问管理员列表返回 401')]
    public function guest_cannot_list_admins(): void
    {
        $response = $this->getJson('/admin/admins');
        $response->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取管理员列表返回 200 与分页数据')]
    public function admin_can_list_admins(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/admins');
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按关键字搜索管理员')]
    public function admin_can_search_admins_by_keyword(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/admins?keyword=admin');
        $response->assertOk()
            ->assertJsonPath('data.0.username', 'admin');
    }

    #[Test]
    #[TestDox('按状态过滤管理员')]
    public function admin_can_filter_admins_by_status(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/admins?status=1');
        $response->assertOk()
            ->assertJsonPath('data.0.username', 'admin');
    }

    #[Test]
    #[TestDox('按角色过滤管理员')]
    public function admin_can_filter_admins_by_role(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/admins?role=super_admin');
        $response->assertOk()
            ->assertJsonPath('data.0.username', 'admin');
    }

    #[Test]
    #[TestDox('按登录IP过滤管理员')]
    public function admin_can_filter_admins_by_login_ip(): void
    {
        $this->admin->update(['last_login_ip' => '192.168.1.1']);

        $response = $this->actingAsAdmin()->getJson('/admin/admins?last_login_ip=192.168');
        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    #[TestDox('创建管理员返回 201')]
    public function admin_can_create_admin(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/admins', [
            'username' => 'editor01',
            'name' => '编辑用户',
            'password' => 'Password123!',
            'status' => 1,
            'roles' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('username', 'editor01')
            ->assertJsonPath('name', '编辑用户');

        $this->assertDatabaseHas('admin_users', [
            'username' => 'editor01',
            'name' => '编辑用户',
        ]);
    }

    #[Test]
    #[TestDox('创建管理员时可分配角色')]
    public function admin_can_create_admin_with_roles(): void
    {
        $editorRole = Role::create(['name' => 'editor', 'display_name' => '编辑', 'guard_name' => AdminMenu::GUARD_NAME]);

        $response = $this->actingAsAdmin()->postJson('/admin/admins', [
            'username' => 'editor02',
            'name' => '编辑二号',
            'password' => 'Password123!',
            'status' => 1,
            'roles' => ['editor'],
        ]);

        $response->assertCreated();

        $newAdmin = Admin::where('username', 'editor02')->first();
        $this->assertTrue($newAdmin->hasRole($editorRole));
    }

    #[Test]
    #[TestDox('创建管理员时用户名必填返回 422')]
    public function create_admin_requires_username(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/admins', [
            'name' => 'Test User',
            'password' => 'Password123!',
            'status' => 1,
        ]);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    #[Test]
    #[TestDox('创建管理员时用户名不能重复返回 422')]
    public function create_admin_username_must_be_unique(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/admins', [
            'username' => 'admin',
            'name' => 'Duplicate',
            'password' => 'Password123!',
            'status' => 1,
        ]);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    #[Test]
    #[TestDox('获取管理员详情')]
    public function admin_can_view_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson("/admin/admins/{$this->admin->id}");
        $response->assertOk()
            ->assertJsonPath('id', $this->admin->id)
            ->assertJsonPath('username', 'admin');
    }

    #[Test]
    #[TestDox('更新管理员成功')]
    public function admin_can_update_admin(): void
    {
        $newAdmin = Admin::create([
            'username' => 'updatable',
            'name' => '更新前',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$newAdmin->id}", [
            'name' => '更新后',
            'email' => 'updated@example.com',
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('name', '更新后')
            ->assertJsonPath('email', 'updated@example.com');

        $this->assertDatabaseHas('admin_users', [
            'id' => $newAdmin->id,
            'name' => '更新后',
            'email' => 'updated@example.com',
        ]);
    }

    #[Test]
    #[TestDox('更新管理员时留空密码则不修改密码')]
    public function update_admin_skips_empty_password(): void
    {
        $newAdmin = Admin::create([
            'username' => 'keep_pwd',
            'name' => 'Keep Password',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);
        $originalPassword = $newAdmin->password;

        $this->actingAsAdmin()->putJson("/admin/admins/{$newAdmin->id}", [
            'name' => 'Keep Password',
            'password' => '',
            'status' => 1,
        ]);

        $this->assertSame($originalPassword, $newAdmin->fresh()->password);
    }

    #[Test]
    #[TestDox('删除管理员返回 204')]
    public function admin_can_delete_admin(): void
    {
        $newAdmin = Admin::create([
            'username' => 'deletable',
            'name' => 'Deletable',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);

        $response = $this->actingAsAdmin()->deleteJson("/admin/admins/{$newAdmin->id}");
        $response->assertNoContent();

        $this->assertSoftDeleted('admin_users', ['id' => $newAdmin->id]);
    }

    #[Test]
    #[TestDox('不能删除自己')]
    public function admin_cannot_delete_self(): void
    {
        $response = $this->actingAsAdmin()->deleteJson("/admin/admins/{$this->admin->id}");
        $response->assertForbidden();
    }

    #[Test]
    #[TestDox('不能删除超级管理员')]
    public function cannot_delete_super_admin(): void
    {
        $superRole = Role::where('name', 'super_admin')->where('guard_name', AdminMenu::GUARD_NAME)->first();
        $target = Admin::create([
            'username' => 'super2',
            'name' => 'Second Super',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);
        $target->assignRole($superRole);

        $response = $this->actingAsAdmin()->deleteJson("/admin/admins/{$target->id}");
        $response->assertForbidden();
    }

    #[Test]
    #[TestDox('获取管理员角色列表')]
    public function can_get_admin_roles(): void
    {
        $response = $this->actingAsAdmin()->getJson("/admin/admins/{$this->admin->id}/roles");
        $response->assertOk()
            ->assertJson(['super_admin']);
    }

    #[Test]
    #[TestDox('分配管理员角色')]
    public function can_assign_roles_to_admin(): void
    {
        $target = Admin::create([
            'username' => 'assign_test',
            'name' => 'Assign Test',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);
        Role::create(['name' => 'viewer', 'display_name' => 'Viewer', 'guard_name' => AdminMenu::GUARD_NAME]);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$target->id}/roles", [
            'roles' => ['viewer'],
        ]);

        $response->assertOk()
            ->assertJson(['viewer']);
        $this->assertTrue($target->fresh()->hasRole('viewer'));
    }

    #[Test]
    #[TestDox('分配角色时 roles 字段必填')]
    public function assign_roles_requires_roles_field(): void
    {
        $target = Admin::create([
            'username' => 'assign_fail',
            'name' => 'Assign Fail',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$target->id}/roles", []);
        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('启用/禁用管理员')]
    public function admin_can_toggle_status(): void
    {
        $target = Admin::create([
            'username' => 'toggle_test',
            'name' => 'Toggle Test',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$target->id}/toggle-status");
        $response->assertOk();
        $this->assertTrue($target->fresh()->status->value === 0);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$target->id}/toggle-status");
        $this->assertTrue($target->fresh()->status->value === 1);
    }

    #[Test]
    #[TestDox('不能禁用自己')]
    public function admin_cannot_disable_self(): void
    {
        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$this->admin->id}/toggle-status");
        $response->assertForbidden();
    }

    #[Test]
    #[TestDox('重置管理员密码')]
    public function admin_can_reset_password(): void
    {
        $target = Admin::create([
            'username' => 'reset_pwd',
            'name' => 'Reset Pwd',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$target->id}/reset-password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('NewPassword123!', $target->fresh()->password));
    }

    #[Test]
    #[TestDox('重置密码需要确认密码')]
    public function reset_password_requires_confirmation(): void
    {
        $target = Admin::create([
            'username' => 'reset_fail',
            'name' => 'Reset Fail',
            'password' => Hash::make('Password123!'),
            'status' => 1,
        ]);

        $response = $this->actingAsAdmin()->putJson("/admin/admins/{$target->id}/reset-password", [
            'password' => 'NewPassword123!',
        ]);
        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('修改当前管理员密码')]
    public function admin_can_change_own_password(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/admins/change-password', [
            'old_password' => '12345678',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('NewPassword123!', $this->admin->fresh()->password));
    }

    #[Test]
    #[TestDox('修改密码时旧密码错误返回 422')]
    public function change_password_requires_correct_old_password(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/admins/change-password', [
            'old_password' => 'wrong_old_password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('获取当前管理员资料')]
    public function admin_can_view_profile(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/admins/profile');
        $response->assertOk()
            ->assertJsonPath('id', $this->admin->id)
            ->assertJsonPath('username', 'admin');
    }

    #[Test]
    #[TestDox('更新当前管理员资料')]
    public function admin_can_update_profile(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/admins/profile', [
            'name' => 'New Name',
            'email' => 'newprofile@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('email', 'newprofile@example.com');

        $this->assertDatabaseHas('admin_users', [
            'id' => $this->admin->id,
            'name' => 'New Name',
            'email' => 'newprofile@example.com',
        ]);
    }

    #[Test]
    #[TestDox('获取管理员登录历史返回分页数据')]
    public function admin_can_view_login_histories(): void
    {
        $this->admin->loginHistories()->create([
            'ip' => '10.0.0.1',
            'login_at' => now(),
        ]);

        $response = $this->actingAsAdmin()->getJson("/admin/admins/{$this->admin->id}/login-histories");
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('未登录不能访问管理员接口')]
    public function unauthenticated_access_returns_401(): void
    {
        $this->getJson('/admin/admins/profile')->assertUnauthorized();
        $this->postJson('/admin/admins', [])->assertUnauthorized();
    }
}
