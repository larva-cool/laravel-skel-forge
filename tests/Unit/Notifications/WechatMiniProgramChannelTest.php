<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\WechatMiniProgramChannel;
use App\Services\WechatMiniService;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * 小程序客服消息通知通道单元测试
 */
#[CoversClass(WechatMiniProgramChannel::class)]
#[Group('notifications')]
#[Group('wechat')]
class WechatMiniProgramChannelTest extends TestCase
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

    /**
     * @return array{0: WechatMiniProgramChannel, 1: FakeWechatMiniCsService}
     */
    private function makeChannel(bool $stubResult = true): array
    {
        $service = new FakeWechatMiniCsService($stubResult);
        $channel = new WechatMiniProgramChannel($service);

        return [$channel, $service];
    }

    #[Test]
    #[TestDox('通知类未实现 toWechatMiniProgram 时抛出 RuntimeException')]
    public function throws_when_notification_missing_method(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('必须实现 toWechatMiniProgram() 方法');

        $channel->send(new MiniCsNotifiableWithRoute, new EmptyCsNotification);
    }

    #[Test]
    #[TestDox('notifiable 未绑定 openid 时直接跳过，返回 false')]
    public function skips_when_openid_missing(): void
    {
        [$channel, $service] = $this->makeChannel();

        // EmptyMiniCsNotifiable 没有 route 方法、也不是 Model
        $result = $channel->send(new EmptyMiniCsNotifiable, new TextCsNotification('hi'));

        $this->assertFalse($result);
        $this->assertSame([], $service->lastCall);
    }

    #[Test]
    #[TestDox('routeNotificationForWechatMiniProgram 返回 openid 时优先采用')]
    public function prefers_explicit_route_method(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(new MiniCsNotifiableWithRoute, new TextCsNotification('你好'));

        $this->assertTrue($result);
        $this->assertSame('o_mini_cs_explicit', $service->lastCall['params']['touser']);
        $this->assertSame([
            'msgtype' => 'text',
            'text' => ['content' => '你好'],
            'touser' => 'o_mini_cs_explicit',
        ], $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('array 形态的图文消息会原样透传到 custom/send')]
    public function array_payload_is_forwarded_as_is(): void
    {
        [$channel, $service] = $this->makeChannel();

        $linkPayload = [
            'msgtype' => 'link',
            'link' => [
                'title' => '欢迎回来',
                'description' => '点击查看详情',
                'url' => 'https://example.com/promo',
                'thumb_url' => 'https://example.com/thumb.jpg',
            ],
        ];

        $result = $channel->send(new MiniCsNotifiableWithRoute, new GenericCsNotification($linkPayload));

        $this->assertTrue($result);
        $expected = $linkPayload;
        $expected['touser'] = 'o_mini_cs_explicit';
        $this->assertSame($expected, $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('array 载荷必须包含 msgtype，否则抛出 RuntimeException')]
    public function array_payload_requires_msgtype(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('包含 msgtype');

        $channel->send(new MiniCsNotifiableWithRoute, new GenericCsNotification(['text' => ['content' => 'no msgtype']]));
    }

    #[Test]
    #[TestDox('非 string 非 array 载荷抛出 RuntimeException')]
    public function invalid_payload_throws(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('string 或包含 msgtype');

        $channel->send(new MiniCsNotifiableWithRoute, new InvalidCsNotification);
    }

    #[Test]
    #[TestDox('errcode 非 0 时返回 false')]
    public function returns_false_when_errcode_is_not_zero(): void
    {
        [$channel] = $this->makeChannel(false);

        $result = $channel->send(new MiniCsNotifiableWithRoute, new TextCsNotification('hi'));

        $this->assertFalse($result);
    }
}

/**
 * 不含 toWechatMiniProgram 的占位通知
 */
class EmptyCsNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['wechat_mini_program'];
    }
}

/**
 * 文本客服消息通知
 */
class TextCsNotification extends Notification
{
    public function __construct(public readonly string $content) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_mini_program'];
    }

    public function toWechatMiniProgram(mixed $notifiable): string
    {
        return $this->content;
    }
}

/**
 * 透传任意客服消息体
 */
class GenericCsNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_mini_program'];
    }

    public function toWechatMiniProgram(mixed $notifiable): array
    {
        return $this->payload;
    }
}

/**
 * 非法类型载荷
 */
class InvalidCsNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['wechat_mini_program'];
    }

    public function toWechatMiniProgram(mixed $notifiable): int
    {
        return 1;
    }
}

/**
 * 通过 routeNotificationForWechatMiniProgram 返回 openid 的 notifiable
 */
class MiniCsNotifiableWithRoute
{
    public function routeNotificationForWechatMiniProgram($notification): string
    {
        return 'o_mini_cs_explicit';
    }
}

/**
 * 不带任何 openid 解析路径的 notifiable
 */
class EmptyMiniCsNotifiable
{
    // 故意保持空类
}

/**
 * 拦截 sendMessage 的 WechatMiniService 子类
 */
class FakeWechatMiniCsService extends WechatMiniService
{
    /**
     * @var array<string, mixed>|array<empty, empty>
     */
    public array $lastCall = [];

    public function __construct(private readonly bool $stubResult) {}

    public function sendMessage(string $toUser, array $message): bool
    {
        // 与生产 WechatMiniService::sendMessage 保持一致：把 touser 注入到 message
        $message['touser'] = $toUser;
        $this->lastCall = ['params' => $message];

        return $this->stubResult;
    }
}
