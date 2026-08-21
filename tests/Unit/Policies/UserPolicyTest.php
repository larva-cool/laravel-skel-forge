<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * UserPolicy 单元测试
 */
#[CoversClass(UserPolicy::class)]
#[Group('policies')]
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy;
    }

    private function setSetting(string $key, mixed $value): void
    {
        app(\App\Services\SettingManagerService::class)->set($key, $value);
    }

    #[Test]
    #[TestDox('register 默认允许注册')]
    public function register_allows_by_default(): void
    {
        $response = $this->policy->register(null);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    #[TestDox('register 配置关闭时拒绝注册')]
    public function register_denies_when_disabled(): void
    {
        $this->setSetting('user.enable_register', false);

        $response = $this->policy->register(null);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('注册功能已关闭', $response->message());
    }

    #[Test]
    #[TestDox('phoneRegister 默认允许手机注册')]
    public function phone_register_allows_by_default(): void
    {
        $response = $this->policy->phoneRegister(null);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    #[TestDox('phoneRegister 配置关闭时拒绝手机注册')]
    public function phone_register_denies_when_disabled(): void
    {
        $this->setSetting('user.enable_phone_register', false);

        $response = $this->policy->phoneRegister(null);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('手机注册已关闭', $response->message());
    }

    #[Test]
    #[TestDox('phoneLogin 默认允许手机登录')]
    public function phone_login_allows_by_default(): void
    {
        $response = $this->policy->phoneLogin(null);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    #[TestDox('phoneLogin 配置关闭时拒绝手机登录')]
    public function phone_login_denies_when_disabled(): void
    {
        $this->setSetting('user.enable_phone_login', false);

        $response = $this->policy->phoneLogin(null);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('手机登录已关闭', $response->message());
    }

    #[Test]
    #[TestDox('passwordLogin 默认允许密码登录')]
    public function password_login_allows_by_default(): void
    {
        $response = $this->policy->passwordLogin(null);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    #[TestDox('passwordLogin 配置关闭时拒绝密码登录')]
    public function password_login_denies_when_disabled(): void
    {
        $this->setSetting('user.enable_password_login', false);

        $response = $this->policy->passwordLogin(null);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('密码登录已关闭', $response->message());
    }
}
