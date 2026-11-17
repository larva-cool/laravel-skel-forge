<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Auth;

use App\Enums\Auth\SocialProvider;
use App\Http\Requests\Admin\Auth\WechatMpLoginRequest;
use App\Models\Admin\Admin;
use App\Models\System\Social;
use App\Services\WechatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Contracts\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 管理员微信公众号登录请求测试
 */
#[CoversClass(WechatMpLoginRequest::class)]
#[Group('admin')]
#[Group('auth')]
class WechatMpLoginRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 模拟的微信 openid
     */
    private string $openid = 'wx-openid-001';

    /**
     * 绑定模拟的微信服务到容器，避免真实调用微信接口
     */
    private function fakeWechatService(): void
    {
        $socialiteUser = Mockery::mock(UserInterface::class);
        $socialiteUser->shouldReceive('getId')->andReturn($this->openid);
        $socialiteUser->shouldReceive('getRaw')->andReturn([]);
        $socialiteUser->shouldReceive('getAccessToken')->andReturn('new-access-token');
        $socialiteUser->shouldReceive('getRefreshToken')->andReturn('new-refresh-token');
        $socialiteUser->shouldReceive('getExpiresIn')->andReturn(7200);

        $oauth = Mockery::mock(ProviderInterface::class);
        $oauth->shouldReceive('userFromCode')->andReturn($socialiteUser);

        $service = Mockery::mock(WechatService::class);
        $service->shouldReceive('getOAuth')->andReturn($oauth);

        $this->app->instance(WechatService::class, $service);
    }

    /**
     * 创建登录请求实例
     */
    private function makeRequest(): WechatMpLoginRequest
    {
        return WechatMpLoginRequest::create('/admin/auth/wx-login', 'POST', ['code' => 'test-code']);
    }

    /**
     * 为管理员绑定微信公众号社交账号
     */
    private function bindSocialFor(Admin $admin): void
    {
        Social::query()->create([
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => $this->openid,
            'unionid' => 'union-id-001',
            'access_token' => 'old-access-token',
            'refresh_token' => '',
        ]);
    }

    /**
     * 创建一个管理员
     *
     * @param  int  $status  状态：0 禁用 / 1 正常
     */
    private function createAdmin(string $username, int $status): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'name' => $username,
            'password' => bcrypt('12345678'),
            'status' => $status,
        ]);
    }

    #[Test]
    #[TestDox('被禁用的管理员微信登录返回 admin.blocked 验证错误并清空 token')]
    public function disabled_admin_cannot_login_via_wechat(): void
    {
        $admin = $this->createAdmin('wx_disabled_admin', 0);
        $admin->createToken('admin-token', ['admin']);
        $this->bindSocialFor($admin);
        $this->fakeWechatService();

        try {
            $this->makeRequest()->authenticate();
            $this->fail('被禁用的管理员应抛出 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame([trans('admin.blocked')], $e->errors()['code']);
        }

        $this->assertSame(0, $admin->tokens()->count());
    }

    #[Test]
    #[TestDox('正常管理员微信登录返回绑定的账号并更新授权凭证')]
    public function active_admin_can_login_via_wechat(): void
    {
        $admin = $this->createAdmin('wx_active_admin', 1);
        $this->bindSocialFor($admin);
        $this->fakeWechatService();

        $result = $this->makeRequest()->authenticate();

        $this->assertNotNull($result);
        $this->assertSame($admin->id, $result->id);
        $this->assertDatabaseHas('socials', [
            'openid' => $this->openid,
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);
    }
}
