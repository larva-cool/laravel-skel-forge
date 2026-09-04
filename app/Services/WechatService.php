<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use EasyWeChat\OfficialAccount\Application;
use EasyWeChat\OfficialAccount\Server;
use EasyWeChat\OfficialAccount\Utils;
use Illuminate\Support\Facades\Log;
use Overtrue\Socialite\Contracts\ProviderInterface;

/**
 * 微信公众号服务
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WechatService
{
    /**
     * 临时二维码最大有效期（秒）
     */
    protected const MAX_TEMPORARY_QRCODE_EXPIRE_SECONDS = 30 * 86400;

    /**
     * 临时二维码默认有效期（秒）
     */
    protected const DEFAULT_TEMPORARY_QRCODE_EXPIRE_SECONDS = 7 * 86400;

    /**
     * 公众号 AppId
     */
    protected readonly string $appId;

    /**
     * 公众号应用实例，首次使用时创建
     */
    protected ?Application $wechatApp = null;

    /**
     * 构造函数
     *
     * @throws InvalidConfigException 当 app_id 或 app_secret 未配置时
     */
    public function __construct()
    {
        $this->appId = (string) config('services.wechat.app_id');

        if ($this->appId === '' || (string) config('services.wechat.app_secret') === '') {
            throw new InvalidConfigException('微信公众号未配置，请设置 WECHAT_APP_ID 与 WECHAT_APP_SECRET');
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
     * 获取微信用户信息
     *
     * @param  string  $openid  用户 openid
     * @return array<string, mixed> 用户信息，失败时返回含 errcode 的数组
     */
    public function getUserInfo(string $openid): array
    {
        $response = $this->call('get', 'cgi-bin/user/info', ['openid' => $openid, 'lang' => 'zh_CN']);

        if (isset($response['openid'])) {
            $response['id'] = $response['openid'];
            $response['unionid'] ??= '';
        }

        return $response;
    }

    /**
     * 批量获取用户基本信息
     *
     * @param  array<int, string>  $openids  openid 列表，单次最多 100 个
     * @param  string  $lang  国家语言版本，zh_CN / zh_TW / en
     * @return array<int, array<string, mixed>> 用户信息列表，失败时返回空数组
     */
    public function getUserInfoBatch(array $openids, string $lang = 'zh_CN'): array
    {
        $users = array_values(array_filter($openids, static fn (string $openid): bool => $openid !== ''));

        if ($users === []) {
            return [];
        }

        $response = $this->call('postJson', 'cgi-bin/user/info/batchget', [
            'user_list' => array_map(static fn (string $openid): array => [
                'openid' => $openid,
                'lang' => $lang,
            ], $users),
        ]);

        return $response['user_info_list'] ?? [];
    }

    /**
     * 获取关注用户列表
     *
     * 一次拉取最多 10000 个关注者，单次返回的 next_openid 即下一次请求的起点。
     *
     * @param  string|null  $nextOpenid  起始 openid，首次拉取传 null
     * @return array<string, mixed> 含 total / count / data / next_openid 的分页结果
     */
    public function listFollowers(?string $nextOpenid = null): array
    {
        $params = [];

        if ($nextOpenid !== null && $nextOpenid !== '') {
            $params['next_openid'] = $nextOpenid;
        }

        $response = $this->call('get', 'cgi-bin/user/get', $params);

        $response['data'] = $response['data']['openid'] ?? [];

        return $response;
    }

    /**
     * 转换 openid（公众号 / 服务号迁移场景）
     *
     * 用于将原账号下关注用户 / 授权关系用户 / 卡券关联用户的 openid 转换为新账号下的 openid。
     * 仅在原账号为非个人主体、且审核完成后调用，迁移完成 15 天后该接口失效。
     * 原账号取原始 id（gh_ 开头），不是 appid。
     *
     * @param  string  $fromAppId  原账号原始 id（gh_xxxx 形式）
     * @param  array<int, string>  $openids  原账号下的 openid 列表，单次最多 100 个
     * @return array<int, array<string, mixed>> 含 ori_openid / new_openid / err_msg 的转换结果
     */
    public function changeOpenid(string $fromAppId, array $openids): array
    {
        $openidList = array_values(array_filter($openids, static fn (string $openid): bool => $openid !== ''));

        if ($openidList === []) {
            return [];
        }

        $response = $this->call('postJson', 'cgi-bin/changeopenid', [
            'from_appid' => $fromAppId,
            'openid_list' => $openidList,
        ]);

        return $response['result_list'] ?? [];
    }

    /**
     * 拉黑一批用户
     *
     * @param  array<int, string>  $openids  openid 列表，单次最多 20 个
     */
    public function batchBlacklist(array $openids): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/tags/members/batchblacklist', [
            'openid_list' => array_values($openids),
        ]));
    }

    /**
     * 取消拉黑一批用户
     *
     * @param  array<int, string>  $openids  openid 列表，单次最多 20 个
     */
    public function batchUnblacklist(array $openids): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/tags/members/batchunblacklist', [
            'openid_list' => array_values($openids),
        ]));
    }

    /**
     * 获取公众号的黑名单列表
     *
     * 一次拉取最多 10000 个 openid，单次返回的 next_openid 即下一次请求的起点。
     *
     * @param  string|null  $nextOpenid  起始 openid，首次拉取传 null
     * @return array<string, mixed> 含 total / count / data / next_openid 的分页结果
     */
    public function getBlacklist(?string $nextOpenid = null): array
    {
        $params = [
            'begin_openid' => $nextOpenid ?? '',
        ];

        $response = $this->call('postJson', 'cgi-bin/tags/members/getblacklist', $params);

        $response['data'] = $response['data']['openid'] ?? [];

        return $response;
    }

    /**
     * 为指定用户设置备注名
     */
    public function updateUserRemark(string $openid, string $remark): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/user/info/updateremark', [
            'openid' => $openid,
            'remark' => $remark,
        ]));
    }

    /**
     * 创建公众号标签
     *
     * @return array<string, mixed> 含 tag 的创建结果，失败时返回空数组
     */
    public function createTag(string $name): array
    {
        $response = $this->call('postJson', 'cgi-bin/tags/create', [
            'tag' => ['name' => $name],
        ]);

        return $response['tag'] ?? [];
    }

    /**
     * 获取公众号已创建的标签列表
     *
     * @return array<int, array<string, mixed>> 标签列表
     */
    public function getTags(): array
    {
        $response = $this->call('get', 'cgi-bin/tags/get');

        return $response['tags'] ?? [];
    }

    /**
     * 编辑标签
     */
    public function updateTag(int $tagId, string $name): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/tags/update', [
            'tag' => ['id' => $tagId, 'name' => $name],
        ]));
    }

    /**
     * 删除标签
     */
    public function deleteTag(int $tagId): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/tags/delete', [
            'tag' => ['id' => $tagId],
        ]));
    }

    /**
     * 批量为用户打标签
     *
     * @param  array<int, string>  $openids  openid 列表，单次最多 50 个
     */
    public function batchTag(array $openids, int $tagId): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/tags/members/batchtagging', [
            'openid_list' => array_values($openids),
            'tagid' => $tagId,
        ]));
    }

    /**
     * 批量为用户取消标签
     *
     * @param  array<int, string>  $openids  openid 列表，单次最多 50 个
     */
    public function batchUntag(array $openids, int $tagId): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/tags/members/batchuntagging', [
            'openid_list' => array_values($openids),
            'tagid' => $tagId,
        ]));
    }

    /**
     * 获取用户身上的标签列表
     *
     * @return array<int, int> 标签 id 列表，失败时返回空数组
     */
    public function getUserTagIds(string $openid): array
    {
        $response = $this->call('postJson', 'cgi-bin/tags/getidlist', ['openid' => $openid]);

        return $response['tagid_list'] ?? [];
    }

    /**
     * 获取标签下粉丝列表
     *
     * @param  string|null  $nextOpenid  起始 openid，首次拉取传 null
     * @return array<string, mixed> 含 count / data / next_openid 的分页结果
     */
    public function getTagFans(int $tagId, ?string $nextOpenid = null): array
    {
        $params = [
            'tagid' => $tagId,
            'next_openid' => $nextOpenid ?? '',
        ];

        $response = $this->call('postJson', 'cgi-bin/user/tag/get', $params);

        $response['data'] = $response['data']['openid'] ?? [];

        return $response;
    }

    /**
     * 发送模板消息
     *
     * @param  array<string, mixed>  $params  模板消息参数
     */
    public function sendTemplateMessage(array $params): bool
    {
        return $this->isSuccessful($this->call('postJson', 'cgi-bin/message/template/send', $params));
    }

    /**
     * 创建临时二维码
     *
     * @param  string|int  $sceneValue  场景值，整数走 scene_id，字符串走 scene_str
     * @param  int|null  $expireSeconds  有效期秒数，默认 7 天，最长 30 天
     * @return array<string, mixed> 含 ticket 的二维码信息，失败时为空数组
     */
    public function createTemporaryQrcode(string|int $sceneValue, ?int $expireSeconds = null): array
    {
        $useSceneId = is_int($sceneValue) && $sceneValue > 0;

        return $this->createQrcode(
            actionName: $useSceneId ? 'QR_SCENE' : 'QR_STR_SCENE',
            actionInfo: [$useSceneId ? 'scene_id' : 'scene_str' => $sceneValue],
            temporary: true,
            expireSeconds: $expireSeconds,
        );
    }

    /**
     * 创建永久二维码
     *
     * @param  string|int  $sceneValue  场景值，1-100000 的整数走 scene_id，其余走 scene_str
     * @return array<string, mixed> 含 ticket 的二维码信息，失败时为空数组
     */
    public function createForeverQrcode(string|int $sceneValue): array
    {
        $useSceneId = is_int($sceneValue) && $sceneValue > 0 && $sceneValue < 100000;

        return $this->createQrcode(
            actionName: $useSceneId ? 'QR_SCENE' : 'QR_STR_SCENE',
            actionInfo: [$useSceneId ? 'scene_id' : 'scene_str' => $sceneValue],
            temporary: false,
        );
    }

    /**
     * 创建二维码
     *
     * @param  string  $actionName  二维码类型，QR_SCENE / QR_STR_SCENE / QR_LIMIT_SCENE 等
     * @param  array<string, mixed>  $actionInfo  场景信息
     * @param  bool  $temporary  是否为临时二维码
     * @param  int|null  $expireSeconds  临时二维码有效期秒数
     * @return array<string, mixed> 含 ticket 的二维码信息，失败时为空数组
     */
    public function createQrcode(string $actionName, array $actionInfo, bool $temporary = true, ?int $expireSeconds = null): array
    {
        $params = [
            'action_name' => $actionName,
            'action_info' => ['scene' => $actionInfo],
        ];

        if ($temporary) {
            $params['expire_seconds'] = min(
                $expireSeconds ?? self::DEFAULT_TEMPORARY_QRCODE_EXPIRE_SECONDS,
                self::MAX_TEMPORARY_QRCODE_EXPIRE_SECONDS,
            );
        }

        $response = $this->call('postJson', 'cgi-bin/qrcode/create', $params);

        return isset($response['ticket']) ? $response : [];
    }

    /**
     * 发送文本客服消息
     *
     * @param  string  $toUser  接收者 openid
     * @param  string  $content  文本内容
     */
    public function sendTextMessage(string $toUser, string $content): bool
    {
        return $this->sendMessage($toUser, [
            'msgtype' => 'text',
            'text' => ['content' => $content],
        ]);
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
     * 构建 JS SDK 配置
     *
     * @param  string  $url  当前页面 URL
     * @param  array<int, string>  $jsApiList  需要使用的 JS 接口列表
     * @param  array<int, string>  $openTagList  需要使用的开放标签列表
     * @param  bool  $debug  是否开启调试模式
     * @return array<string, mixed>
     */
    public function buildJsSdkConfig(string $url, array $jsApiList = [], array $openTagList = [], bool $debug = false): array
    {
        return $this->getUtils()->buildJsSdkConfig($url, $jsApiList, $openTagList, $debug);
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
     * 获取消息服务端，绑定当前 HTTP 请求以便解析微信推送
     */
    public function getServer(): Server
    {
        $app = $this->getApp();
        $app->setRequestFromSymfonyRequest(app('request'));

        return $app->getServer();
    }

    /**
     * 获取网页授权服务
     */
    public function getOAuth(): ProviderInterface
    {
        return $this->getApp()->getOAuth();
    }

    /**
     * 获取工具类
     */
    public function getUtils(): Utils
    {
        return $this->getApp()->getUtils();
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
            'secret' => config('services.wechat.app_secret'),
            'token' => config('services.wechat.token'),
            'aes_key' => config('services.wechat.aes_key'),
            'use_stable_access_token' => config('services.wechat.use_stable_access_token', true),

            'oauth' => [
                'scopes' => ['snsapi_userinfo'],
                'redirect_url' => config('services.wechat.oauth_redirect_url', '/wechat/auth-callback'),
            ],

            'http' => [
                'timeout' => (float) config('services.wechat.http_timeout', 5.0),
                'base_uri' => config('services.wechat.base_uri', 'https://api.weixin.qq.com/'),
                'retry' => true,
            ],
        ];
    }
}
