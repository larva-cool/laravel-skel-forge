<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WechatMiniService;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use EasyWeChat\MiniApp\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * WechatMiniService 单元测试
 */
#[CoversClass(WechatMiniService::class)]
#[Group('services')]
#[Group('wechat')]
class WechatMiniServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wechat_mini.app_id' => 'wx_mini_appid',
            'services.wechat_mini.app_secret' => 'mini_secret',
            'services.wechat_mini.token' => 'mini_token',
            'services.wechat_mini.aes_key' => null,
        ]);
    }

    #[Test]
    #[TestDox('未配置 app_id 时构造抛出配置异常')]
    public function constructor_throws_when_app_id_missing(): void
    {
        config(['services.wechat_mini.app_id' => null]);

        $this->expectException(InvalidConfigException::class);

        new WechatMiniService;
    }

    #[Test]
    #[TestDox('未配置 app_secret 时构造抛出配置异常')]
    public function constructor_throws_when_app_secret_missing(): void
    {
        config(['services.wechat_mini.app_secret' => '']);

        $this->expectException(InvalidConfigException::class);

        new WechatMiniService;
    }

    #[Test]
    #[TestDox('getAppid 返回小程序配置的 app_id')]
    public function get_appid_returns_configured_value(): void
    {
        $this->assertSame('wx_mini_appid', (new WechatMiniService)->getAppid());
    }

    #[Test]
    #[TestDox('make 返回小程序服务而非公众号服务')]
    public function make_returns_mini_service_instance(): void
    {
        $this->assertInstanceOf(WechatMiniService::class, WechatMiniService::make());
    }

    #[Test]
    #[TestDox('make 从容器解析出同一个单例')]
    public function make_resolves_singleton_from_container(): void
    {
        $this->assertSame(WechatMiniService::make(), WechatMiniService::make());
    }

    #[Test]
    #[TestDox('getApp 复用同一个应用实例')]
    public function get_app_is_memoized(): void
    {
        $service = new WechatMiniService;

        $this->assertSame($service->getApp(), $service->getApp());
    }

    #[Test]
    #[TestDox('应用实例使用小程序配置的 appid 与 secret')]
    public function app_uses_configured_credentials(): void
    {
        $account = (new WechatMiniService)->getApp()->getAccount();

        $this->assertSame('wx_mini_appid', $account->getAppId());
        $this->assertSame('mini_secret', $account->getSecret());
    }

    #[Test]
    #[TestDox('getUtils 返回小程序命名空间下的工具类')]
    public function get_utils_returns_mini_app_utils(): void
    {
        $this->assertInstanceOf(Utils::class, (new WechatMiniService)->getUtils());
    }
}
