<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * API V1 认证控制器功能测试
 */
#[CoversClass(AuthController::class)]
#[Group('api')]
#[Group('auth')]
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make($this->password),
        ]);

        // 测试环境 REMOTE_PORT 为 null，LoginSucceeded 事件需要 string
        $this->withServerVariables(['REMOTE_PORT' => '12345']);
    }

    #[Test]
    #[TestDox('密码登录成功返回 200 和 token')]
    public function password_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'expires_in',
                'user' => ['user_id', 'user_name', 'email', 'phone', 'avatar'],
            ])
            ->assertJsonPath('user.user_name', 'testuser');
    }

    #[Test]
    #[TestDox('密码登录密码错误返回 422')]
    public function password_login_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => 'wrong_password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    #[TestDox('密码登录用户不存在返回 422')]
    public function password_login_with_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'no_such_user',
            'password' => $this->password,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    #[TestDox('密码登录缺少必填字段返回 422')]
    public function password_login_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    #[TestDox('密码登录密码过短返回 422')]
    public function password_login_password_too_short(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => '12345',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    #[TestDox('冻结用户密码登录返回 422')]
    public function frozen_user_cannot_login(): void
    {
        $this->user->markFrozen();

        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['account']);
    }

    #[Test]
    #[TestDox('邮箱账号密码登录成功')]
    public function password_login_with_email_account(): void
    {
        $this->user->update(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'test@example.com',
            'password' => $this->password,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.user_id', $this->user->id);
    }

    #[Test]
    #[TestDox('退出登录返回 204')]
    public function logout_successfully(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/logout');

        $response->assertNoContent();
    }

    #[Test]
    #[TestDox('退出登录后 token 从数据库删除')]
    public function token_deleted_after_logout(): void
    {
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $token = $loginResponse->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    #[TestDox('未认证访问刷新令牌返回 401')]
    public function guest_cannot_refresh_token(): void
    {
        $this->postJson('/api/v1/auth/refresh-token')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('刷新令牌返回新 token')]
    public function refresh_token_returns_new_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ]);

        $oldToken = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'expires_in', 'user']);

        // 新 token 与旧 token 不同
        $this->assertNotEquals($oldToken, $response->json('access_token'));
    }

    #[Test]
    #[TestDox('冻结用户刷新令牌返回 422')]
    public function frozen_user_cannot_refresh_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ]);

        $token = $loginResponse->json('access_token');

        $this->user->markFrozen();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['account']);
    }

    #[Test]
    #[TestDox('获取已签发的 token 列表')]
    public function authenticated_user_can_list_tokens(): void
    {
        // 创建两个 token
        $this->user->createToken('device1', ['user']);
        $this->user->createToken('device2', ['user']);

        $token = $this->postJson('/api/v1/auth/login', [
            'account' => 'testuser',
            'password' => $this->password,
        ])->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/tokens');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('未认证访问 token 列表返回 401')]
    public function guest_cannot_list_tokens(): void
    {
        $this->getJson('/api/v1/auth/tokens')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('手机登录手机号格式不正确返回 422')]
    public function phone_login_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/v1/auth/phone-login', [
            'phone' => 'invalid',
            'verify_code' => '123456',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    #[TestDox('手机登录验证码格式不正确返回 422')]
    public function phone_login_invalid_verify_code_format(): void
    {
        $response = $this->postJson('/api/v1/auth/phone-login', [
            'phone' => '13800000000',
            'verify_code' => 'abc',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['verify_code']);
    }

    #[Test]
    #[TestDox('手机登录缺少必填字段返回 422')]
    public function phone_login_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/phone-login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'verify_code']);
    }
}
