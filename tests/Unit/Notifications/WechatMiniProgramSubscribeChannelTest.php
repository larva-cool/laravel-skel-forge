<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\WechatMiniProgramSubscribeChannel;
use App\Services\WechatMiniService;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * 小程序订阅消息通知通道单元测试
 */
#[CoversClass(WechatMiniProgramSubscribeChannel::class)]
#[Group('notifications')]
#[Group('wechat')]
class WechatMiniProgramSubscribeChannelTest extends TestCase
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
     * @return array{0: WechatMiniProgramSubscribeChannel, 1: FakeWechatMiniChannelService}
     */
    private function makeChannel(bool $stubResult = true): array
    {
        $service = new FakeWechatMiniChannelService($stubResult);
        $channel = new WechatMiniProgramSubscribeChannel($service);

        return [$channel, $service];
    }

    #[Test]
    #[TestDox('通知类未实现 toWechatMiniProgramSubscribe 时抛出 RuntimeException')]
    public function throws_when_notification_missing_method(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('必须实现 toWechatMiniProgramSubscribe() 方法');

        $channel->send(new MiniNotifiableWithRoute, new EmptyMiniSubscribeNotification);
    }

    #[Test]
    #[TestDox('notifiable 未绑定 openid 时直接跳过，返回 false')]
    public function skips_when_openid_missing(): void
    {
        [$channel, $service] = $this->makeChannel();

        // EmptyMiniNotifiable 没有 route 方法、也不是 Model，会走完所有兜底路径后返回 false
        $result = $channel->send(new EmptyMiniNotifiable, new SubscribeNotification(
            templateId: 'TPL_ORDER',
            data: ['thing1' => '商品', 'amount1' => '99.00'],
        ));

        $this->assertFalse($result);
        $this->assertSame([], $service->lastCall);
    }

    #[Test]
    #[TestDox('routeNotificationForWechatMiniProgramSubscribe 返回 openid 时优先采用')]
    public function prefers_explicit_route_method(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL_ORDER',
            data: ['thing1' => '商品'],
        ));

        $this->assertTrue($result);
        $this->assertSame('TPL_ORDER', $service->lastCall['params']['template_id']);
        $this->assertSame('o_mini_explicit', $service->lastCall['params']['touser']);
        $this->assertSame(['thing1' => ['value' => '商品']], $service->lastCall['params']['data']);
    }

    #[Test]
    #[TestDox('string 形态载荷直接作为 template_id 发送')]
    public function string_payload_used_as_template_id(): void
    {
        [$channel, $service] = $this->makeChannel();

        $result = $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL_BARE',
            data: [],
            useString: true,
        ));

        $this->assertTrue($result);
        $this->assertSame('TPL_BARE', $service->lastCall['params']['template_id']);
        $this->assertArrayNotHasKey('data', $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('data 为扁平键值时自动包成 {value: ...} 形态')]
    public function data_is_wrapped_with_value_key(): void
    {
        [$channel, $service] = $this->makeChannel();

        $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL',
            data: ['name' => '张三', 'time' => '2026-09-01'],
        ));

        $this->assertSame([
            'name' => ['value' => '张三'],
            'time' => ['value' => '2026-09-01'],
        ], $service->lastCall['params']['data']);
    }

    #[Test]
    #[TestDox('data 已含 value 键时保持原样不重复包')]
    public function data_already_in_canonical_form_is_kept(): void
    {
        [$channel, $service] = $this->makeChannel();

        $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL',
            data: ['thing1' => ['value' => '商品', 'color' => '#ff0000']],
        ));

        $this->assertSame(
            ['thing1' => ['value' => '商品', 'color' => '#ff0000']],
            $service->lastCall['params']['data'],
        );
    }

    #[Test]
    #[TestDox('page 与 miniprogram_state 字段会被原样透传')]
    public function page_and_state_are_passed_through(): void
    {
        [$channel, $service] = $this->makeChannel();

        $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL',
            data: ['k' => 'v'],
            page: 'pages/order/detail?id=1',
            miniprogramState: 'developer',
        ));

        $this->assertSame('pages/order/detail?id=1', $service->lastCall['params']['page']);
        $this->assertSame('developer', $service->lastCall['params']['miniprogram_state']);
    }

    #[Test]
    #[TestDox('miniprogram_state 取值非法时忽略该字段')]
    public function invalid_miniprogram_state_is_dropped(): void
    {
        [$channel, $service] = $this->makeChannel();

        $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL',
            data: ['k' => 'v'],
            miniprogramState: 'bogus',
        ));

        $this->assertArrayNotHasKey('miniprogram_state', $service->lastCall['params']);
    }

    #[Test]
    #[TestDox('缺少 template_id 时抛出 RuntimeException')]
    public function template_id_required(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('缺少 template_id');

        $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: '',
            data: ['k' => 'v'],
        ));
    }

    #[Test]
    #[TestDox('data 为空数组时抛出 RuntimeException')]
    public function data_required(): void
    {
        [$channel] = $this->makeChannel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('缺少 data 字段');

        $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL',
            data: [],
        ));
    }

    #[Test]
    #[TestDox('errcode 非 0 时返回 false')]
    public function returns_false_when_errcode_is_not_zero(): void
    {
        [$channel] = $this->makeChannel(false);

        $result = $channel->send(new MiniNotifiableWithRoute, new SubscribeNotification(
            templateId: 'TPL',
            data: ['k' => 'v'],
        ));

        $this->assertFalse($result);
    }
}

/**
 * 不含 toWechatMiniProgramSubscribe 的占位通知
 */
class EmptyMiniSubscribeNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['wechat_mini_program_subscribe'];
    }
}

/**
 * 订阅消息通知
 */
class SubscribeNotification extends Notification
{
    /**
     * @param  array<int|string, mixed>  $data
     */
    public function __construct(
        public readonly string $templateId,
        public readonly array $data,
        public readonly ?string $page = null,
        public readonly ?string $miniprogramState = null,
        public readonly bool $useString = false,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['wechat_mini_program_subscribe'];
    }

    public function toWechatMiniProgramSubscribe(mixed $notifiable): string|array
    {
        if ($this->useString) {
            return $this->templateId;
        }

        return [
            'template_id' => $this->templateId,
            'data' => $this->data,
            'page' => $this->page,
            'miniprogram_state' => $this->miniprogramState,
        ];
    }
}

/**
 * 通过 routeNotificationForWechatMiniProgramSubscribe 返回 openid 的 notifiable
 */
class MiniNotifiableWithRoute
{
    public function routeNotificationForWechatMiniProgramSubscribe($notification): string
    {
        return 'o_mini_explicit';
    }
}

/**
 * 不带任何 openid 解析路径的 notifiable
 */
class EmptyMiniNotifiable
{
    // 故意保持空类
}

/**
 * 拦截 sendTemplateMessage 的 WechatMiniService 子类
 */
class FakeWechatMiniChannelService extends WechatMiniService
{
    /**
     * @var array<string, mixed>|array<empty, empty>
     */
    public array $lastCall = [];

    public function __construct(private readonly bool $stubResult) {}

    public function sendTemplateMessage(array $params): bool
    {
        $this->lastCall = ['params' => $params];

        return $this->stubResult;
    }
}
