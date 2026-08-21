<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 后台权限中间件可用性测试
 *
 * 验证 permission 中间件在 admin guard 下能够正确放行与拦截。
 */
#[Group('admin')]
#[Group('permission')]
class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 拥有全部权限的超级管理员
     */
    private Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::query()->where('username', 'admin')->first();
    }

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
    #[TestDox('permission 中间件别名已注册')]
    public function permission_middleware_alias_is_registered(): void
    {
        $aliases = app('router')->getMiddleware();

        $this->assertArrayHasKey('permission', $aliases);
        $this->assertSame(PermissionMiddleware::class, $aliases['permission']);
    }

    #[Test]
    #[TestDox('权限记录使用 admin guard')]
    public function permissions_are_registered_under_admin_guard(): void
    {
        $this->assertSame(
            0,
            Permission::query()->where('guard_name', '!=', AdminMenu::GUARD_NAME)->count(),
            '存在非 admin guard 的权限记录，permission 中间件将无法匹配'
        );

        $this->assertSame('admin', (new Admin)->guard_name ?? 'admin');
    }

    #[Test]
    #[TestDox('权限中间件已挂载到后台控制器上')]
    public function permission_middleware_is_attached_to_admin_controllers(): void
    {
        $controllers = [
            \App\Http\Controllers\Admin\UserController::class,
            \App\Http\Controllers\Admin\AdminController::class,
            \App\Http\Controllers\Admin\RoleController::class,
            \App\Http\Controllers\Admin\MenuController::class,
            \App\Http\Controllers\Admin\SettingController::class,
            \App\Http\Controllers\Admin\AreaController::class,
            \App\Http\Controllers\Admin\PhoneCodeController::class,
            \App\Http\Controllers\Admin\MailCodeController::class,
            \App\Http\Controllers\Admin\MonitorController::class,
        ];

        foreach ($controllers as $controller) {
            $middleware = array_column((new $controller)->getMiddleware(), 'middleware');
            $permissionMiddleware = array_filter($middleware, fn ($m) => str_starts_with((string) $m, 'permission:'));

            $this->assertNotEmpty($permissionMiddleware, "{$controller} 未挂载 permission 中间件");
        }
    }

    #[Test]
    #[TestDox('超级管理员可访问受权限保护的接口')]
    public function super_admin_can_access_protected_endpoints(): void
    {
        $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/admin/users')
            ->assertOk();
    }

    #[Test]
    #[TestDox('未登录访问受权限保护的接口返回 401')]
    public function guest_gets_unauthorized(): void
    {
        $this->getJson('/admin/users')->assertUnauthorized();
    }

    /**
     * 权限缺失场景数据
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function forbiddenEndpointProvider(): array
    {
        return [
            '用户列表' => ['getJson', '/admin/users', 'users.index'],
            '管理员列表' => ['getJson', '/admin/admins', 'admins.index'],
            '角色列表' => ['getJson', '/admin/roles', 'roles.index'],
            '菜单列表' => ['getJson', '/admin/menus', 'menus.index'],
            '配置列表' => ['getJson', '/admin/settings', 'settings.index'],
            '地区列表' => ['getJson', '/admin/areas', 'areas.index'],
            '短信验证码列表' => ['getJson', '/admin/phone-codes', 'phone-codes.index'],
            '邮件验证码列表' => ['getJson', '/admin/mail-codes', 'mail-codes.index'],
            '全部权限列表' => ['getJson', '/admin/roles/permissions', 'roles.index'],
            '服务器监控' => ['getJson', '/admin/monitor/servers', 'pulse.index'],
            '缓存监控' => ['getJson', '/admin/monitor/cache', 'pulse.index'],
        ];
    }

    #[Test]
    #[TestDox('缺少对应权限时访问接口返回 403')]
    #[DataProvider('forbiddenEndpointProvider')]
    public function admin_without_permission_gets_forbidden(string $method, string $uri, string $requiredPermission): void
    {
        // 授予一个无关权限，确保管理员已登录且有角色，仅缺少目标权限
        $admin = $this->createAdminWithPermissions(['attachments.create']);

        $this->actingAs($admin, 'admin')
            ->{$method}($uri)
            ->assertForbidden();

        $this->assertTrue(
            Permission::query()->where('name', $requiredPermission)->where('guard_name', AdminMenu::GUARD_NAME)->exists(),
            "权限 {$requiredPermission} 未在 admin guard 下注册"
        );
    }

    #[Test]
    #[TestDox('拥有只读权限时可查看但不可修改')]
    public function admin_with_index_permission_cannot_edit(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);
        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/users')
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->putJson("/admin/users/{$user->id}/adjust-balance", [
                'type' => 'points',
                'amount' => 100,
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->deleteJson("/admin/users/{$user->id}")
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('拥有编辑权限时可调整余额')]
    public function admin_with_edit_permission_can_adjust_balance(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index', 'users.edit']);
        $user = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->putJson("/admin/users/{$user->id}/adjust-balance", [
                'type' => 'points',
                'amount' => 100,
            ])
            ->assertOk();

        $this->assertSame(100, $user->fresh()->available_points);
    }

    #[Test]
    #[TestDox('不受权限保护的接口在仅登录时可访问')]
    public function unprotected_endpoints_remain_accessible(): void
    {
        $admin = $this->createAdminWithPermissions([]);

        $this->actingAs($admin, 'admin')->getJson('/admin/auth/info')->assertOk();
        $this->actingAs($admin, 'admin')->getJson('/admin/admins/profile')->assertOk();
        $this->actingAs($admin, 'admin')->getJson('/admin/routes')->assertOk();
    }
}
