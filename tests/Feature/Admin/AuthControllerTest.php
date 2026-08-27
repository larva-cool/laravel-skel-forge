<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuthController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台认证控制器功能测试
 */
#[CoversClass(AuthController::class)]
#[Group('admin')]
#[Group('auth')]
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试使用的管理员密码（与迁移中初始数据一致）
     */
    private string $adminPassword = '12345678';

    #[Test]
    #[TestDox('管理员使用正确凭证登录返回 200 和 token')]
    public function login_with_valid_credentials(): void
    {
        $response = $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => $this->adminPassword,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'user' => ['user_id', 'user_name', 'email', 'avatar', 'roles', 'buttons'],
            ]);
    }

    #[Test]
    #[TestDox('管理员使用错误密码登录返回 422 验证错误')]
    public function login_with_wrong_password(): void
    {
        $response = $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => 'wrong_password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.password.0', trans('auth.failed'));
    }

    #[Test]
    #[TestDox('登录缺少必填字段返回 422 验证错误')]
    public function login_with_missing_fields(): void
    {
        $response = $this->postJson('/admin/auth/login', [
            'account' => 'admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => ['password'],
            ]);
    }

    #[Test]
    #[TestDox('使用有效 token 获取用户信息返回 200 和完整信息')]
    public function info_with_valid_token(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();

        $response = $this->actingAs($admin, 'admin')->getJson('/admin/auth/info');

        $response->assertOk()
            ->assertJsonStructure([
                'user_id',
                'user_name',
                'email',
                'avatar',
                'roles',
                'buttons',
            ]);
    }

    #[Test]
    #[TestDox('未登录时获取用户信息返回 401')]
    public function info_without_token(): void
    {
        $response = $this->getJson('/admin/auth/info');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    #[TestDox('退出登录返回 204')]
    public function logout_successfully(): void
    {
        $loginResponse = $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => $this->adminPassword,
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/admin/auth/logout');

        $response->assertNoContent();
    }

    #[Test]
    #[TestDox('退出登录后 token 从数据库删除')]
    public function token_deleted_after_logout(): void
    {
        $loginResponse = $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => $this->adminPassword,
        ]);

        $token = $loginResponse->json('access_token');

        // 退出前 token 存在
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/admin/auth/logout')
            ->assertNoContent();

        // 退出后 token 已从数据库删除
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    #[TestDox('被禁用的管理员账号返回验证错误')]
    public function login_with_disabled_admin(): void
    {
        Admin::query()->create([
            'username' => 'disabled_user',
            'name' => 'Disabled User',
            'password' => bcrypt($this->adminPassword),
            'status' => 0,
        ]);

        $response = $this->postJson('/admin/auth/login', [
            'account' => 'disabled_user',
            'password' => $this->adminPassword,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.account.0', trans('admin.blocked'));
    }

    #[Test]
    #[TestDox('密码错误的登录尝试写入 result 为 false 的登录历史')]
    public function failed_login_is_recorded_in_login_histories(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();

        $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => 'wrong_password',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'result' => false,
        ]);

        // 登录失败不应影响登录统计
        $admin->refresh();
        $this->assertSame(0, $admin->login_count);
        $this->assertNull($admin->last_login_at);
    }

    #[Test]
    #[TestDox('被禁用管理员的登录尝试写入 result 为 false 的登录历史')]
    public function disabled_admin_login_is_recorded_in_login_histories(): void
    {
        $admin = Admin::query()->create([
            'username' => 'disabled_user',
            'name' => 'Disabled User',
            'password' => bcrypt($this->adminPassword),
            'status' => 0,
        ]);

        $this->postJson('/admin/auth/login', [
            'account' => 'disabled_user',
            'password' => $this->adminPassword,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'result' => false,
        ]);
    }

    #[Test]
    #[TestDox('不存在的账号登录尝试不写入登录历史')]
    public function login_with_unknown_account_is_not_recorded(): void
    {
        $this->postJson('/admin/auth/login', [
            'account' => 'not_exists_account',
            'password' => $this->adminPassword,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('login_histories', 0);
    }

    #[Test]
    #[TestDox('登录成功写入 result 为 true 的登录历史并更新登录统计')]
    public function successful_login_is_recorded_in_login_histories(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();

        $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => $this->adminPassword,
        ])->assertOk();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'result' => true,
        ]);

        $admin->refresh();
        $this->assertSame(1, $admin->login_count);
        $this->assertNotNull($admin->last_login_at);
    }

    #[Test]
    #[TestDox('登录尝试超过节流上限返回 429')]
    public function login_is_throttled_after_too_many_attempts(): void
    {
        $limit = (int) explode(',', (string) settings('admin.login_throttle', '10,3'))[0];

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/admin/auth/login', [
                'account' => 'admin',
                'password' => 'wrong_password',
            ])->assertUnprocessable();
        }

        $this->postJson('/admin/auth/login', [
            'account' => 'admin',
            'password' => 'wrong_password',
        ])->assertStatus(429);
    }
}
