<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use EasyWeChat\MiniApp\Application;
use EasyWeChat\MiniApp\Server;
use EasyWeChat\MiniApp\Utils;

/**
 * 微信小程序服务
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class WechatMiniService
{
    /**
     * 小程序 AppId
     */
    protected readonly string $appId;

    /**
     * 小程序应用实例，首次使用时创建
     */
    protected ?Application $wechatApp = null;

    /**
     * 构造函数
     *
     * @throws InvalidConfigException 当 app_id 或 app_secret 未配置时
     */
    public function __construct()
    {
        $this->appId = (string) config('services.wechat_mini.app_id');

        if ($this->appId === '' || (string) config('services.wechat_mini.app_secret') === '') {
            throw new InvalidConfigException('微信小程序未配置，请设置 WECHAT_MINI_APP_ID 与 WECHAT_MINI_APP_SECRET');
        }
    }

    /**
     * 获取服务实例
     */
    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * 获取应用实例
     */
    public function getApp(): Application
    {
        if ($this->wechatApp === null) {
            $this->wechatApp = new Application($this->getConfig());
            $this->wechatApp->setCache(app('cache.store'));
        }

        return $this->wechatApp;
    }

    /**
     * 获取 AppId
     */
    public function getAppid(): string
    {
        return $this->appId;
    }

    /**
     * 获取工具类
     */
    public function getUtils(): Utils
    {
        return $this->getApp()->getUtils();
    }

    /**
     * 获取消息服务端，绑定当前 HTTP 请求以便解析微信推送
     */
    public function getServer(): Server
    {
        $app = $this->getApp();
        $app->setRequestFromSymfonyRequest(app('request'));

        return $app->getServer();
    }

    /**
     * 获取 EasyWeChat 配置
     *
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        return [
            'app_id' => $this->appId,
            'secret' => config('services.wechat_mini.app_secret'),
            'token' => config('services.wechat_mini.token'),
            'aes_key' => config('services.wechat_mini.aes_key'),
            'use_stable_access_token' => config('services.wechat_mini.use_stable_access_token', true),

            'http' => [
                'timeout' => (float) config('services.wechat_mini.http_timeout', 5.0),
                'base_uri' => config('services.wechat_mini.base_uri', 'https://api.weixin.qq.com/'),
                'retry' => true,
            ],
        ];
    }
}
