<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Admin;

use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\LoginHistory;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\NewAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin 模型单元测试
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[CoversClass(Admin::class)]
#[Group('models')]
class AdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个 Admin 实例用于测试。
     */
    private function makeAdmin(array $attributes = []): Admin
    {
        return Admin::create(array_merge([
            'username' => 'admin_'.uniqid(),
            'name' => '测试管理员',
            'password' => 'secret123',
            'status' => 1,
        ], $attributes));
    }

    /**
     * 测试创建管理员
     */
    #[Test]
    #[TestDox('创建管理员并验证基础属性')]
    public function create_admin_persists_to_database(): void
    {
        $admin = $this->makeAdmin([
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'phone' => '13800138000',
            'name' => '超级管理员',
        ]);

        $this->assertInstanceOf(Admin::class, $admin);
        $this->assertDatabaseHas('admin_users', [
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'phone' => '13800138000',
            'name' => '超级管理员',
        ]);
    }

    /**
     * 测试 ID 自动递增（迁移中配置从 10000000 起始，SQLite 测试环境下忽略起始值，仅验证自增）
     */
    #[Test]
    #[TestDox('ID 字段为自增主键')]
    public function id_is_auto_incrementing_primary_key(): void
    {
        $admin1 = $this->makeAdmin(['username' => 'admin_a']);
        $admin2 = $this->makeAdmin(['username' => 'admin_b']);

        $this->assertIsInt($admin1->id);
        $this->assertIsInt($admin2->id);
        $this->assertGreaterThan($admin1->id, $admin2->id);
    }

    /**
     * 测试字段类型转换
     */
    #[Test]
    #[TestDox('字段 casts 正确转换类型')]
    public function attributes_are_cast_to_correct_types(): void
    {
        $now = Carbon::create(2025, 6, 1, 10, 0, 0);
        Carbon::setTestNow($now);

        $admin = $this->makeAdmin([
            'login_count' => '5',
            'status' => '1',
            'last_login_ip' => '192.168.1.1',
            'last_login_at' => $now,
            'last_active_at' => $now,
        ]);

        $this->assertIsInt($admin->id);
        $this->assertInstanceOf(\App\Enums\AdminStatus::class, $admin->status);
        $this->assertSame(1, $admin->status->value);
        $this->assertIsInt($admin->login_count);
        $this->assertSame(5, $admin->login_count);
        $this->assertInstanceOf(Carbon::class, $admin->last_login_at);
        $this->assertInstanceOf(Carbon::class, $admin->last_active_at);
        $this->assertInstanceOf(Carbon::class, $admin->created_at);
        $this->assertInstanceOf(Carbon::class, $admin->updated_at);

        Carbon::setTestNow();
    }

    /**
     * 测试可空字段默认值
     */
    #[Test]
    #[TestDox('创建管理员时可空字段初始为 null')]
    public function nullable_fields_default_to_null(): void
    {
        $admin = Admin::create([
            'username' => 'defaults_admin',
            'password' => 'secret',
        ]);

        $this->assertNull($admin->email);
        $this->assertNull($admin->phone);
        $this->assertNull($admin->name);
        $this->assertNull($admin->last_login_ip);
        $this->assertNull($admin->last_login_at);
        $this->assertNull($admin->last_active_at);
        $this->assertNull($admin->remember_token);
    }

    /**
     * 测试密码自动哈希
     */
    #[Test]
    #[TestDox('password 字段写入时自动哈希')]
    public function password_is_auto_hashed(): void
    {
        $admin = $this->makeAdmin(['password' => 'plain-password']);

        $this->assertNotSame('plain-password', $admin->password);
        $this->assertTrue(password_verify('plain-password', $admin->password));
    }

    /**
     * 测试序列化时隐藏敏感字段
     */
    #[Test]
    #[TestDox('toArray 序列化时隐藏 password 和 remember_token')]
    public function sensitive_fields_are_hidden_in_array(): void
    {
        $admin = $this->makeAdmin();
        $array = $admin->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    /**
     * 测试 username 唯一约束
     */
    #[Test]
    #[TestDox('重复 username 插入抛出异常')]
    public function duplicate_username_violates_unique_constraint(): void
    {
        $this->makeAdmin(['username' => 'duplicate_user']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeAdmin(['username' => 'duplicate_user']);
    }

    /**
     * 测试 email 唯一约束
     */
    #[Test]
    #[TestDox('重复 email 插入抛出异常')]
    public function duplicate_email_violates_unique_constraint(): void
    {
        $this->makeAdmin(['email' => 'same@example.com']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeAdmin([
            'username' => 'another_user',
            'email' => 'same@example.com',
        ]);
    }

    /**
     * 测试 loginHistories 多态关联
     */
    #[Test]
    #[TestDox('loginHistories 多态关联返回管理员的登录历史，按 login_at 倒序')]
    public function login_histories_relation_returns_morph_many_ordered_by_login_at_desc(): void
    {
        $admin = $this->makeAdmin();

        // 直接写库避免触发 LoginHistory::created 事件干扰 login_count 断言
        $older = LoginHistory::factory()->make([
            'ip' => '10.0.0.1',
            'login_at' => Carbon::create(2025, 1, 1, 9, 0, 0),
        ]);
        $older->user_id = $admin->id;
        $older->user_type = $admin->getMorphClass();
        $older->saveQuietly();

        $newer = LoginHistory::factory()->make([
            'ip' => '10.0.0.2',
            'login_at' => Carbon::create(2025, 1, 2, 9, 0, 0),
        ]);
        $newer->user_id = $admin->id;
        $newer->user_type = $admin->getMorphClass();
        $newer->saveQuietly();

        $histories = $admin->loginHistories;

        $this->assertCount(2, $histories);
        $this->assertInstanceOf(LoginHistory::class, $histories->first());
        $this->assertSame('10.0.0.2', $histories->first()->ip);
        $this->assertSame('10.0.0.1', $histories->last()->ip);
    }

    /**
     * 测试创建登录历史自动更新管理员登录信息
     */
    #[Test]
    #[TestDox('通过 LoginHistory 创建记录时自动递增管理员 login_count 并更新最后登录信息')]
    public function creating_login_history_updates_admin_login_tracking(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 10, 30, 0));

        $admin = $this->makeAdmin(['login_count' => 0]);

        LoginHistory::factory()->create([
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'ip' => '203.0.113.1',
        ]);

        $admin->refresh();
        $this->assertSame(1, $admin->login_count);
        $this->assertSame('203.0.113.1', $admin->last_login_ip);
        $this->assertSame('2025-06-15 10:30:00', $admin->last_login_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 测试 resetPassword：重置密码、刷新 remember_token 并派发 PasswordReset 事件
     */
    #[Test]
    #[TestDox('resetPassword 重置密码、刷新 remember_token 并派发 PasswordReset 事件')]
    public function reset_password_updates_password_token_and_dispatches_event(): void
    {
        Event::fake();

        $admin = $this->makeAdmin(['password' => 'old-password']);
        $originalToken = $admin->remember_token;

        $admin->resetPassword('new-strong-password');

        $admin->refresh();
        $this->assertTrue(password_verify('new-strong-password', $admin->password));
        $this->assertNotSame($originalToken, $admin->remember_token);
        $this->assertNotEmpty($admin->remember_token);
        $this->assertSame(60, strlen($admin->remember_token));

        Event::assertDispatched(PasswordReset::class, function (PasswordReset $event) use ($admin) {
            return $event->user->id === $admin->id && $event->user instanceof Admin;
        });
    }

    /**
     * 测试 fillable 字段可批量赋值
     */
    #[Test]
    #[TestDox('username/email/phone/name 等 fillable 字段可批量写入')]
    public function fillable_attributes_can_be_mass_assigned(): void
    {
        $admin = new Admin([
            'username' => 'mass_assigned',
            'email' => 'mass@example.com',
            'phone' => '13900139000',
            'name' => '批量赋值',
            'status' => 1,
            'password' => 'pwd',
        ]);
        $admin->save();

        $this->assertSame('mass_assigned', $admin->username);
        $this->assertSame('mass@example.com', $admin->email);
        $this->assertSame('13900139000', $admin->phone);
        $this->assertSame('批量赋值', $admin->name);
    }

    /**
     * 测试使用 username 作为认证标识
     */
    #[Test]
    #[TestDox('管理员继承 Authenticatable，可用于认证')]
    public function admin_extends_authenticatable(): void
    {
        $admin = $this->makeAdmin();

        $this->assertInstanceOf(\Illuminate\Foundation\Auth\User::class, $admin);
        $this->assertTrue(method_exists($admin, 'getAuthIdentifier'));
        $this->assertSame($admin->id, $admin->getAuthIdentifier());
    }

    /**
     * 测试管理员使用 admin guard 作为 Spatie 权限 guard
     */
    #[Test]
    #[TestDox('HasRoles trait 使用 admin 作为默认 guard_name，创建的权限归属 admin guard')]
    public function roles_and_permissions_use_admin_guard(): void
    {
        $admin = $this->makeAdmin();

        $permission = Permission::findOrCreate('guard:check', AdminMenu::GUARD_NAME);

        $admin->givePermissionTo($permission);

        $permissions = $admin->permissions;
        $this->assertCount(1, $permissions);
        $this->assertSame(AdminMenu::GUARD_NAME, $permissions->first()->guard_name);
    }

    /**
     * 测试管理员可分配直接权限
     */
    #[Test]
    #[TestDox('管理员可被赋予直接权限并通过 can() 校验')]
    public function admin_can_be_given_direct_permission(): void
    {
        $admin = $this->makeAdmin();

        Permission::findOrCreate('admin:add', AdminMenu::GUARD_NAME);

        $this->assertFalse($admin->can('admin:add'));

        $admin->givePermissionTo('admin:add');

        $this->assertTrue($admin->can('admin:add'));
        $this->assertTrue($admin->hasPermissionTo('admin:add', AdminMenu::GUARD_NAME));
    }

    /**
     * 测试管理员可分配角色并继承角色权限
     */
    #[Test]
    #[TestDox('管理员通过角色继承权限')]
    public function admin_inherits_permissions_from_role(): void
    {
        $admin = $this->makeAdmin();

        $role = Role::findOrCreate('editor', AdminMenu::GUARD_NAME);
        Permission::findOrCreate('menu:edit', AdminMenu::GUARD_NAME);
        $role->givePermissionTo('menu:edit');

        $admin->assignRole($role);

        $this->assertTrue($admin->hasRole('editor'));
        $this->assertTrue($admin->can('menu:edit'));
    }

    /**
     * 测试管理员可创建 Sanctum API Token
     */
    #[Test]
    #[TestDox('管理员可通过 HasApiTokens 创建 Sanctum 个人访问令牌')]
    public function admin_can_create_sanctum_token(): void
    {
        $admin = $this->makeAdmin();

        $tokenResult = $admin->createToken('admin-token');

        $this->assertInstanceOf(NewAccessToken::class, $tokenResult);
        $this->assertIsString($tokenResult->plainTextToken);
        $this->assertNotEmpty($tokenResult->plainTextToken);
        $this->assertSame($admin->id, $tokenResult->accessToken->tokenable_id);
        $this->assertSame($admin->getMorphClass(), $tokenResult->accessToken->tokenable_type);
    }

    /**
     * 测试管理员可创建带能力的 Token
     */
    #[Test]
    #[TestDox('创建 Token 时可赋予 abilities 并可校验')]
    public function admin_token_abilities_are_enforced(): void
    {
        $admin = $this->makeAdmin();

        $tokenResult = $admin->createToken('ability-token', ['admin:list', 'admin:view']);

        $this->assertTrue($tokenResult->accessToken->can('admin:list'));
        $this->assertTrue($tokenResult->accessToken->can('admin:view'));
        $this->assertFalse($tokenResult->accessToken->can('admin:delete'));
    }

    /**
     * 测试使用 Sanctum Token 认证后可通过 Auth::guard('admin') 获取当前管理员
     */
    #[Test]
    #[TestDox('Sanctum 可通过 admin guard 解析出 Admin 实例')]
    public function sanctum_authenticates_admin_via_admin_guard(): void
    {
        $admin = $this->makeAdmin();
        $tokenResult = $admin->createToken('guard-test');

        $this->actingAs($admin, 'sanctum');

        $this->assertAuthenticatedAs($admin, 'sanctum');
        $this->assertSame($admin->id, auth('sanctum')->id());
    }

    /**
     * 测试移除角色后权限失效
     */
    #[Test]
    #[TestDox('移除角色后管理员失去该角色的权限')]
    public function removing_role_revokes_inherited_permissions(): void
    {
        $admin = $this->makeAdmin();

        $role = Role::findOrCreate('manager', AdminMenu::GUARD_NAME);
        Permission::findOrCreate('role:delete', AdminMenu::GUARD_NAME);
        $role->givePermissionTo('role:delete');
        $admin->assignRole($role);
        $this->assertTrue($admin->can('role:delete'));

        $admin->removeRole($role);

        $this->assertFalse($admin->hasRole('manager'));
        $this->assertFalse($admin->can('role:delete'));
    }
}
