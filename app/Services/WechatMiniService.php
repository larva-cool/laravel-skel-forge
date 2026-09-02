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
use Illuminate\Support\Facades\Log;

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
     * 发送模板消息
     *
     * @param  array<string, mixed>  $params  模板消息参数
     */
    public function sendTemplateMessage(array $params): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/message/subscribe/send', $params));
    }

    /**
     * 发送客服消息
     *
     * @param  string  $toUser  接收者 openid
     * @param  array<string, mixed>  $message  消息体
     */
    public function sendMessage(string $toUser, array $message): bool
    {
        $message['touser'] = $toUser;

        return $this->isSuccessful($this->call('postJson', 'cgi-bin/message/custom/send', $message));
    }

    /**
     * 重置接口调用次数
     */
    public function clearQuota(): bool
    {
        return $this->isSuccessful($this->call('post', 'cgi-bin/clear_quota', ['appid' => $this->appId]));
    }

    /**
     * 查询接口调用次数
     *
     * @param  string  $cgiPath  接口路径，如 /cgi-bin/message/custom/send
     * @return array<string, mixed> 配额信息，失败时返回含 errcode 的数组
     */
    public function getQuota(string $cgiPath): array
    {
        return $this->call('post', 'cgi-bin/openapi/quota/get', ['cgi_path' => $cgiPath]);
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
     * 调用微信接口，统一处理异常与错误日志
     *
     * @param  string  $method  客户端方法名，get / post / postJson
     * @param  string  $uri  接口路径
     * @param  array<string, mixed>  $params  请求参数
     * @return array<string, mixed> 接口响应，异常时返回 errcode 为 -1 的数组
     */
    protected function call(string $method, string $uri, array $params = []): array
    {
        try {
            $response = $this->getApp()->getClient()->{$method}($uri, $params)->toArray();
        } catch (\Throwable $e) {
            Log::error("微信接口 {$uri} 调用异常：".$e->getMessage(), ['exception' => $e]);

            return ['errcode' => -1, 'errmsg' => $e->getMessage()];
        }

        if (! $this->isSuccessful($response)) {
            Log::error("微信接口 {$uri} 返回错误：".($response['errmsg'] ?? '未知错误'), ['response' => $response]);
        }

        return $response;
    }

    /**
     * 判断接口响应是否成功
     *
     * @param  array<string, mixed>  $response  接口响应
     */
    protected function isSuccessful(array $response): bool
    {
        return (int) ($response['errcode'] ?? 0) === 0;
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
