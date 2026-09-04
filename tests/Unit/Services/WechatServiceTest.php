<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WechatService;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * WechatService 单元测试
 *
 * 仅覆盖参数组装与配置解析等纯逻辑，通过重写 call() 拦截请求，不发起真实网络调用。
 */
#[CoversClass(WechatService::class)]
#[Group('services')]
#[Group('wechat')]
class WechatServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wechat.app_id' => 'wx_test_appid',
            'services.wechat.app_secret' => 'test_secret',
            'services.wechat.token' => 'test_token',
            'services.wechat.aes_key' => null,
        ]);
    }

    /**
     * 创建可拦截接口调用的服务实例
     *
     * @param  array<string, mixed>  $response  桩响应
     */
    private function fakeService(array $response = ['ticket' => 'fake-ticket']): FakeWechatService
    {
        return new FakeWechatService($response);
    }

    #[Test]
    #[TestDox('未配置 app_id 时构造抛出配置异常')]
    public function constructor_throws_when_app_id_missing(): void
    {
        config(['services.wechat.app_id' => null]);

        $this->expectException(InvalidConfigException::class);

        new WechatService;
    }

    #[Test]
    #[TestDox('未配置 app_secret 时构造抛出配置异常')]
    public function constructor_throws_when_app_secret_missing(): void
    {
        config(['services.wechat.app_secret' => '']);

        $this->expectException(InvalidConfigException::class);

        new WechatService;
    }

    #[Test]
    #[TestDox('getAppid 返回配置的 app_id')]
    public function get_appid_returns_configured_value(): void
    {
        $this->assertSame('wx_test_appid', (new WechatService)->getAppid());
    }

    #[Test]
    #[TestDox('make 从容器解析出同一个 scoped 实例')]
    public function make_resolves_scoped_instance_from_container(): void
    {
        $this->assertSame(WechatService::make(), WechatService::make());
    }

    #[Test]
    #[TestDox('createTemporaryQrcode 使用调用方传入的有效期')]
    public function temporary_qrcode_honours_given_expire_seconds(): void
    {
        $service = $this->fakeService();

        $service->createTemporaryQrcode(1024, 3600);

        $this->assertSame(3600, $service->lastCall['params']['expire_seconds']);
    }

    #[Test]
    #[TestDox('createTemporaryQrcode 未传有效期时默认 7 天')]
    public function temporary_qrcode_defaults_to_seven_days(): void
    {
        $service = $this->fakeService();

        $service->createTemporaryQrcode(1024);

        $this->assertSame(7 * 86400, $service->lastCall['params']['expire_seconds']);
    }

    #[Test]
    #[TestDox('createTemporaryQrcode 有效期超过 30 天时被截断')]
    public function temporary_qrcode_caps_expire_seconds_at_thirty_days(): void
    {
        $service = $this->fakeService();

        $service->createTemporaryQrcode(1024, 99 * 86400);

        $this->assertSame(30 * 86400, $service->lastCall['params']['expire_seconds']);
    }

    /**
     * 临时二维码场景值与类型的对应关系
     *
     * @return array<string, array{0: string|int, 1: string, 2: string}>
     */
    public static function temporarySceneProvider(): array
    {
        return [
            '正整数走 scene_id' => [1024, 'QR_SCENE', 'scene_id'],
            '超过十万的整数仍走 scene_id' => [200000, 'QR_SCENE', 'scene_id'],
            '字符串走 scene_str' => ['invite_abc', 'QR_STR_SCENE', 'scene_str'],
        ];
    }

    #[Test]
    #[TestDox('createTemporaryQrcode 按场景值类型选择 action_name')]
    #[DataProvider('temporarySceneProvider')]
    public function temporary_qrcode_resolves_scene_type(string|int $sceneValue, string $actionName, string $sceneKey): void
    {
        $service = $this->fakeService();

        $service->createTemporaryQrcode($sceneValue);

        $this->assertSame($actionName, $service->lastCall['params']['action_name']);
        $this->assertSame([$sceneKey => $sceneValue], $service->lastCall['params']['action_info']['scene']);
    }

    #[Test]
    #[TestDox('createForeverQrcode 不携带 expire_seconds')]
    public function forever_qrcode_omits_expire_seconds(): void
    {
        $service = $this->fakeService();

        $service->createForeverQrcode(99);

        $this->assertArrayNotHasKey('expire_seconds', $service->lastCall['params']);
    }

    /**
     * 永久二维码场景值与类型的对应关系
     *
     * @return array<string, array{0: string|int, 1: string, 2: string}>
     */
    public static function foreverSceneProvider(): array
    {
        return [
            '十万以内的整数走 scene_id' => [99, 'QR_SCENE', 'scene_id'],
            '十万及以上的整数走 scene_str' => [100000, 'QR_STR_SCENE', 'scene_str'],
            '字符串走 scene_str' => ['channel_a', 'QR_STR_SCENE', 'scene_str'],
        ];
    }

    #[Test]
    #[TestDox('createForeverQrcode 按场景值类型选择 action_name')]
    #[DataProvider('foreverSceneProvider')]
    public function forever_qrcode_resolves_scene_type(string|int $sceneValue, string $actionName, string $sceneKey): void
    {
        $service = $this->fakeService();

        $service->createForeverQrcode($sceneValue);

        $this->assertSame($actionName, $service->lastCall['params']['action_name']);
        $this->assertSame([$sceneKey => $sceneValue], $service->lastCall['params']['action_info']['scene']);
    }

    #[Test]
    #[TestDox('二维码接口未返回 ticket 时得到空数组')]
    public function qrcode_returns_empty_array_without_ticket(): void
    {
        $service = $this->fakeService(['errcode' => 40001, 'errmsg' => 'invalid credential']);

        $this->assertSame([], $service->createForeverQrcode('channel_a'));
    }

    #[Test]
    #[TestDox('getUserInfo 补齐 id 与 unionid 字段')]
    public function get_user_info_fills_id_and_unionid(): void
    {
        $service = $this->fakeService(['openid' => 'o_test_123', 'nickname' => '测试用户']);

        $result = $service->getUserInfo('o_test_123');

        $this->assertSame('o_test_123', $result['id']);
        $this->assertSame('', $result['unionid']);
    }

    #[Test]
    #[TestDox('getUserInfo 保留接口返回的 unionid')]
    public function get_user_info_keeps_existing_unionid(): void
    {
        $service = $this->fakeService(['openid' => 'o_test_123', 'unionid' => 'u_test_456']);

        $this->assertSame('u_test_456', $service->getUserInfo('o_test_123')['unionid']);
    }

    #[Test]
    #[TestDox('sendTextMessage 组装 text 类型客服消息')]
    public function send_text_message_builds_text_payload(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->sendTextMessage('o_test_123', '你好'));
        $this->assertSame([
            'msgtype' => 'text',
            'text' => ['content' => '你好'],
            'touser' => 'o_test_123',
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('errcode 非 0 时消息类接口返回 false')]
    public function message_call_returns_false_on_error_code(): void
    {
        $service = $this->fakeService(['errcode' => 45015, 'errmsg' => 'response out of time limit']);

        $this->assertFalse($service->sendTextMessage('o_test_123', '你好'));
    }

    #[Test]
    #[TestDox('响应缺少 errcode 时视为成功')]
    public function message_call_treats_missing_error_code_as_success(): void
    {
        $service = $this->fakeService([]);

        $this->assertTrue($service->sendTemplateMessage(['template_id' => 'tpl_1']));
    }

    #[Test]
    #[TestDox('clearQuota 提交当前公众号 appid')]
    public function clear_quota_submits_current_appid(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->clearQuota());
        $this->assertSame(['appid' => 'wx_test_appid'], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('getQuota 提交查询的接口路径')]
    public function get_quota_submits_cgi_path(): void
    {
        $service = $this->fakeService(['errcode' => 0, 'daily_limit' => 500000]);

        $result = $service->getQuota('/cgi-bin/message/custom/send');

        $this->assertSame(['cgi_path' => '/cgi-bin/message/custom/send'], $service->lastCall['params']);
        $this->assertSame(500000, $result['daily_limit']);
    }

    #[Test]
    #[TestDox('getApp 复用同一个应用实例')]
    public function get_app_is_memoized(): void
    {
        $service = new WechatService;

        $this->assertSame($service->getApp(), $service->getApp());
    }

    #[Test]
    #[TestDox('应用实例使用配置中的 appid 与 secret')]
    public function app_uses_configured_credentials(): void
    {
        $account = (new WechatService)->getApp()->getAccount();

        $this->assertSame('wx_test_appid', $account->getAppId());
        $this->assertSame('test_secret', $account->getSecret());
    }

    #[Test]
    #[TestDox('getUserInfoBatch 组装 user_list 并返回 user_info_list')]
    public function batch_user_info_returns_user_info_list(): void
    {
        $service = $this->fakeService([
            'user_info_list' => [
                ['openid' => 'o_1', 'nickname' => 'A'],
                ['openid' => 'o_2', 'nickname' => 'B'],
            ],
        ]);

        $users = $service->getUserInfoBatch(['o_1', 'o_2'], 'en');

        $this->assertCount(2, $users);
        $this->assertSame('A', $users[0]['nickname']);
        $this->assertSame('postJson', $service->lastCall['method']);
        $this->assertSame('cgi-bin/user/info/batchget', $service->lastCall['uri']);
        $this->assertSame([
            ['openid' => 'o_1', 'lang' => 'en'],
            ['openid' => 'o_2', 'lang' => 'en'],
        ], $service->lastCall['params']['user_list']);
    }

    #[Test]
    #[TestDox('getUserInfoBatch 过滤空 openid 后空列表直接返回空数组')]
    public function batch_user_info_returns_empty_when_no_openids(): void
    {
        $service = $this->fakeService();

        $this->assertSame([], $service->getUserInfoBatch(['']));
        $this->assertSame([], $service->lastCall);
    }

    #[Test]
    #[TestDox('listFollowers 不传 next_openid 时不携带该字段')]
    public function list_followers_omits_next_openid_by_default(): void
    {
        $service = $this->fakeService(['total' => 2, 'count' => 2, 'data' => ['openid' => ['o_1', 'o_2']]]);

        $result = $service->listFollowers();

        $this->assertSame([], $service->lastCall['params']);
        $this->assertSame(['o_1', 'o_2'], $result['data']);
        $this->assertSame('get', $service->lastCall['method']);
        $this->assertSame('cgi-bin/user/get', $service->lastCall['uri']);
    }

    #[Test]
    #[TestDox('listFollowers 传入 next_openid 时附带在请求参数中')]
    public function list_followers_passes_next_openid(): void
    {
        $service = $this->fakeService(['total' => 0, 'count' => 0, 'data' => ['openid' => []]]);

        $service->listFollowers('o_next');

        $this->assertSame(['next_openid' => 'o_next'], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('listFollowers 当 data.openid 缺失时回退为空数组')]
    public function list_followers_falls_back_to_empty_openid_list(): void
    {
        $service = $this->fakeService(['total' => 0, 'count' => 0]);

        $this->assertSame([], $service->listFollowers()['data']);
    }

    #[Test]
    #[TestDox('changeOpenid 提交 from_appid 与 openid_list 并返回 result_list')]
    public function change_openid_returns_result_list(): void
    {
        $service = $this->fakeService([
            'result_list' => [
                ['ori_openid' => 'o_1', 'new_openid' => 'o_new_1', 'err_msg' => 'ok'],
            ],
        ]);

        $result = $service->changeOpenid('gh_original_id', ['o_1', 'o_2']);

        $this->assertSame([
            ['ori_openid' => 'o_1', 'new_openid' => 'o_new_1', 'err_msg' => 'ok'],
        ], $result);
        $this->assertSame('postJson', $service->lastCall['method']);
        $this->assertSame('cgi-bin/changeopenid', $service->lastCall['uri']);
        $this->assertSame([
            'from_appid' => 'gh_original_id',
            'openid_list' => ['o_1', 'o_2'],
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('changeOpenid openid 列表全为空时直接返回空数组不发请求')]
    public function change_openid_returns_empty_when_no_valid_openids(): void
    {
        $service = $this->fakeService();

        $this->assertSame([], $service->changeOpenid('gh_original_id', ['']));
        $this->assertSame([], $service->lastCall);
    }

    #[Test]
    #[TestDox('batchBlacklist 提交 openid_list 并返回成功状态')]
    public function batch_blacklist_submits_openid_list(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->batchBlacklist(['o_1', 'o_2']));
        $this->assertSame('cgi-bin/tags/members/batchblacklist', $service->lastCall['uri']);
        $this->assertSame(['openid_list' => ['o_1', 'o_2']], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('batchUnblacklist 提交 openid_list 并返回成功状态')]
    public function batch_unblacklist_submits_openid_list(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->batchUnblacklist(['o_1']));
        $this->assertSame('cgi-bin/tags/members/batchunblacklist', $service->lastCall['uri']);
        $this->assertSame(['openid_list' => ['o_1']], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('getBlacklist 提交 begin_openid 并展开 data.openid')]
    public function get_blacklist_submits_begin_openid_and_flattens_data(): void
    {
        $service = $this->fakeService(['total' => 2, 'count' => 2, 'data' => ['openid' => ['o_1', 'o_2']]]);

        $result = $service->getBlacklist('o_next');

        $this->assertSame('cgi-bin/tags/members/getblacklist', $service->lastCall['uri']);
        $this->assertSame(['begin_openid' => 'o_next'], $service->lastCall['params']);
        $this->assertSame(['o_1', 'o_2'], $result['data']);
    }

    #[Test]
    #[TestDox('updateUserRemark 提交 openid 与 remark')]
    public function update_user_remark_submits_openid_and_remark(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->updateUserRemark('o_1', 'VIP'));
        $this->assertSame('cgi-bin/user/info/updateremark', $service->lastCall['uri']);
        $this->assertSame(['openid' => 'o_1', 'remark' => 'VIP'], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('createTag 提交名称并返回 tag 字段')]
    public function create_tag_returns_created_tag(): void
    {
        $service = $this->fakeService(['tag' => ['id' => 101, 'name' => 'VIP']]);

        $tag = $service->createTag('VIP');

        $this->assertSame(['id' => 101, 'name' => 'VIP'], $tag);
        $this->assertSame('cgi-bin/tags/create', $service->lastCall['uri']);
        $this->assertSame(['tag' => ['name' => 'VIP']], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('getTags 返回 tags 字段列表')]
    public function get_tags_returns_tags_list(): void
    {
        $service = $this->fakeService(['tags' => [['id' => 1, 'name' => 'A']]]);

        $this->assertSame([['id' => 1, 'name' => 'A']], $service->getTags());
        $this->assertSame('cgi-bin/tags/get', $service->lastCall['uri']);
        $this->assertSame('get', $service->lastCall['method']);
    }

    #[Test]
    #[TestDox('updateTag 提交 id 与 name')]
    public function update_tag_submits_id_and_name(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->updateTag(101, '新 VIP'));
        $this->assertSame('cgi-bin/tags/update', $service->lastCall['uri']);
        $this->assertSame(['tag' => ['id' => 101, 'name' => '新 VIP']], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('deleteTag 提交 id')]
    public function delete_tag_submits_id(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->deleteTag(101));
        $this->assertSame('cgi-bin/tags/delete', $service->lastCall['uri']);
        $this->assertSame(['tag' => ['id' => 101]], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('batchTag 提交 openid_list 与 tagid')]
    public function batch_tag_submits_openids_and_tag_id(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->batchTag(['o_1', 'o_2'], 101));
        $this->assertSame('cgi-bin/tags/members/batchtagging', $service->lastCall['uri']);
        $this->assertSame([
            'openid_list' => ['o_1', 'o_2'],
            'tagid' => 101,
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('batchUntag 提交 openid_list 与 tagid')]
    public function batch_untag_submits_openids_and_tag_id(): void
    {
        $service = $this->fakeService(['errcode' => 0]);

        $this->assertTrue($service->batchUntag(['o_1'], 101));
        $this->assertSame('cgi-bin/tags/members/batchuntagging', $service->lastCall['uri']);
        $this->assertSame([
            'openid_list' => ['o_1'],
            'tagid' => 101,
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('getUserTagIds 返回 tagid_list')]
    public function get_user_tag_ids_returns_tagid_list(): void
    {
        $service = $this->fakeService(['tagid_list' => [101, 102]]);

        $this->assertSame([101, 102], $service->getUserTagIds('o_1'));
        $this->assertSame('cgi-bin/tags/getidlist', $service->lastCall['uri']);
        $this->assertSame(['openid' => 'o_1'], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('getTagFans 提交 tagid 与 next_openid 并展开 data.openid')]
    public function get_tag_fans_submits_tag_and_openid(): void
    {
        $service = $this->fakeService(['count' => 2, 'data' => ['openid' => ['o_1', 'o_2']]]);

        $result = $service->getTagFans(101, 'o_next');

        $this->assertSame('cgi-bin/user/tag/get', $service->lastCall['uri']);
        $this->assertSame([
            'tagid' => 101,
            'next_openid' => 'o_next',
        ], $service->lastCall['params']);
        $this->assertSame(['o_1', 'o_2'], $result['data']);
    }
}

/**
 * 测试替身：拦截 call() 以避免真实网络请求，并记录调用参数
 */
class FakeWechatService extends WechatService
{
    /**
     * 最近一次接口调用记录
     *
     * @var array{method: string, uri: string, params: array<string, mixed>}
     */
    public array $lastCall = [];

    /**
     * @param  array<string, mixed>  $stubResponse  桩响应
     */
    public function __construct(private readonly array $stubResponse)
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function call(string $method, string $uri, array $params = []): array
    {
        $this->lastCall = compact('method', 'uri', 'params');

        return $this->stubResponse;
    }
}
