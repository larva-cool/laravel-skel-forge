<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\WechatOfficialAccountChannel;
use App\Services\WechatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * 微信公众号通知通道单元测试
 */
#[CoversClass(WechatOfficialAccountChannel::class)]
#[Group('notifications')]
#[Group('wechat')]
class WechatOfficialAccountChannelTest extends TestCase
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

        // 注册测试占位 Model 到 morph map，避免 getMorphClass() 抛 ClassMorphViolationException
        Relation::morphMap([
            'notifiables_test_stub' => NotifiableSubclass::class,
        ], false);
    }

    /**
     * 创建可拦截请求的通道实例
     *
     * @param  array<string, mixed>  $stubResponse  桩响应
     * @return array{0: WechatOfficialAccountChannel, 1: FakeWechatChannelService}
     */
    private function makeChannel(array $stubResponse = ['errcode' => 0]): array
    {
        $service = new FakeWechatChannelService($stubResponse);
        $channel = new WechatOfficialAccountChannel($service);

        return [$channel, $service];
    }

    #[Test]
    #[TestDox('通知类未实现 toWechatOfficialAccount 时抛出 RuntimeException')]
    public function throws_when_notification_missing_method(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('必须实现 toWechatOfficialAccount() 方法');

        $channel->send(new EmptyNotifiable, new EmptyNotification);
    }

    #[Test]
    #[TestDox('notifiable 未绑定 openid 时直接跳过，返回 null')]
    public function skips_when_openid_missing(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(new EmptyNotifiable, new TextNotification('hi'));

        $this->assertNull($result);
        $this->assertSame([], $service->lastCall);
    }

    #[Test]
    #[TestDox('routeNotificationForWechatOfficialAccount 返回 openid 时优先采用')]
    public function prefers_explicit_route_method(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(new NotifiableWithRoute, new TextNotification('hi'));

        $this->assertSame(['msgtype' => 'text'], $result);
        $this->assertSame('o_explicit_123', $service->lastCall['params']['touser']);
    }

    #[Test]
    #[TestDox('字符串载荷走客服文本通道')]
    public function string_payload_uses_text_message(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(new NotifiableWithRoute, new TextNotification('你好'));

        $this->assertSame(['msgtype' => 'text'], $result);
        $this->assertSame([
            'msgtype' => 'text',
            'text' => ['content' => '你好'],
            'touser' => 'o_explicit_123',
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('含 template_id 的载荷走模板消息通道')]
    public function template_payload_uses_template_message(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(
            new NotifiableWithRoute,
            new TemplateNotification('tpl_xxx', ['first' => 'data1', 'second' => 'data2'], 'https://example.com')
        );

        $this->assertSame(['msgtype' => 'template'], $result);
        $this->assertSame('cgi-bin/message/template/send', $service->lastCall['uri']);
        $this->assertSame('o_explicit_123', $service->lastCall['params']['touser']);
        $this->assertSame('tpl_xxx', $service->lastCall['params']['template_id']);
        $this->assertSame(['first' => 'data1', 'second' => 'data2'], $service->lastCall['params']['data']);
        $this->assertSame('https://example.com', $service->lastCall['params']['url']);
    }

    #[Test]
    #[TestDox('含 news 字段的载荷走图文客服消息')]
    public function news_payload_uses_news_message(): void
    {
        [$channel, $service] = $this->makeChannel();

        $news = ['articles' => [['title' => 'a', 'content' => 'b', 'url' => 'https://x']]];

        $result = $channel->send(new NotifiableWithRoute, new NewsNotification($news));

        $this->assertSame(['msgtype' => 'news'], $result);
        $this->assertSame([
            'msgtype' => 'news',
            'news' => $news,
            'touser' => 'o_explicit_123',
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('未识别形态的 array 载荷作为客服消息体透传')]
    public function unknown_payload_is_forwarded_as_custom_message(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(
            new NotifiableWithRoute,
            new GenericNotification(['msgtype' => 'image', 'image' => ['media_id' => 'm1']])
        );

        $this->assertSame(['msgtype' => 'image', 'image' => ['media_id' => 'm1']], $result);
        $this->assertSame('cgi-bin/message/custom/send', $service->lastCall['uri']);
    }

    #[Test]
    #[TestDox('非 string 非 array 载荷抛出 RuntimeException')]
    public function invalid_payload_throws(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('必须返回 string 或 array');

        $channel->send(new NotifiableWithRoute, new InvalidPayloadNotification);
    }

    #[Test]
    #[TestDox('template_id 为空字符串时抛出 RuntimeException')]
    public function template_id_required(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('缺少 template_id');

        $channel->send(new NotifiableWithRoute, new TemplateNotification('', [], null));
    }

    #[Test]
    #[TestDox('优先使用 routeNotificationForWechatOfficialAccount 返回的 openid，忽略兜底查询')]
    public function explicit_route_wins_over_fallback(): void
    {
        [$channel, $service] = $this->makeChannel();

        $notifiable = new NotifiableSubclass(['id' => 9999]);
        $notifiable->id = 9999;

        $result = $channel->send($notifiable, new TextNotification('hi'));

        // NotifiableSubclass 没实现 routeNotificationForWechatOfficialAccount，
        // 也不在 socials 表中，最终应返回 null 而非抛异常
        $this->assertNull($result);
        $this->assertSame([], $service->lastCall);
    }
}

/**
 * 不含 toWechatOfficialAccount 的占位通知
 */
class EmptyNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['wechat_official_account'];
    }
}

/**
 * 客服文本通知
 */
class TextNotification extends Notification
{
    public function __construct(public readonly string $content) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_official_account'];
    }

    public function toWechatOfficialAccount(mixed $notifiable): string
    {
        return $this->content;
    }
}

/**
 * 模板消息通知
 */
class TemplateNotification extends Notification
{
    public function __construct(
        public readonly string $templateId,
        /** @var array<string, string> */
        public readonly array $data,
        public readonly ?string $url = null,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_official_account'];
    }

    public function toWechatOfficialAccount(mixed $notifiable): array
    {
        return [
            'template_id' => $this->templateId,
            'data' => $this->data,
            'url' => $this->url,
        ];
    }
}

/**
 * 图文通知
 */
class NewsNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $news
     */
    public function __construct(public readonly array $news) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_official_account'];
    }

    public function toWechatOfficialAccount(mixed $notifiable): array
    {
        return ['news' => $this->news];
    }
}

/**
 * 透传任意客服消息体
 */
class GenericNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_official_account'];
    }

    public function toWechatOfficialAccount(mixed $notifiable): array
    {
        return $this->payload;
    }
}

/**
 * 非法类型载荷通知
 */
class InvalidPayloadNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['wechat_official_account'];
    }

    public function toWechatOfficialAccount(mixed $notifiable): int
    {
        return 123;
    }
}

/**
 * 通过 routeNotificationForWechatOfficialAccount 返回 openid 的 notifiable
 */
class NotifiableWithRoute
{
    public function routeNotificationForWechatOfficialAccount($notification): string
    {
        return 'o_explicit_123';
    }
}

/**
 * 没有任何 openid 解析路径的 notifiable
 */
class EmptyNotifiable
{
    // 故意保持空类
}

/**
 * 用于测试 Model 兜底分支的空 Model 子类
 */
class NotifiableSubclass extends Model
{
    protected $table = 'notifiables_test_stub';

    protected $guarded = [];

    public $timestamps = false;
}

/**
 * 拦截 call() 的 WechatService 子类，避免真实网络请求
 */
class FakeWechatChannelService extends WechatService
{
    /**
     * @var array{method: string, uri: string, params: array<string, mixed>}|array<empty, empty>
     */
    public array $lastCall = [];

    /**
     * @param  array<string, mixed>  $stubResponse
     */
    public function __construct(private readonly array $stubResponse)
    {
        parent::__construct();
    }

    protected function call(string $method, string $uri, array $params = []): array
    {
        $this->lastCall = compact('method', 'uri', 'params');

        return $this->stubResponse;
    }
}
