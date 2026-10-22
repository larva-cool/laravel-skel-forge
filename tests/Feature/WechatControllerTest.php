<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature;

use App\Events\Wechat\WechatSubscribed;
use App\Jobs\Wechat\ReplyWechatTextMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 微信公众号服务端回调测试
 */
#[CoversClass(\App\Http\Controllers\WechatController::class)]
#[Group('wechat')]
class WechatControllerTest extends TestCase
{
    use RefreshDatabase;
    private const TOKEN = 'test_token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wechat.app_id' => 'wx_test_appid',
            'services.wechat.app_secret' => 'test_secret',
            'services.wechat.token' => self::TOKEN,
            'services.wechat.aes_key' => null,
        ]);
    }

    /**
     * 生成明文模式的合法回调签名
     *
     * @return array{signature: string, timestamp: string, nonce: string}
     */
    private function sign(): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));

        $params = [self::TOKEN, $timestamp, $nonce];
        sort($params, SORT_STRING);

        return [
            'signature' => sha1(implode('', $params)),
            'timestamp' => $timestamp,
            'nonce' => $nonce,
        ];
    }

    /**
     * 构造文本消息回调 XML
     */
    private function textMessageXml(string $content, string $msgId = '1234567890123456', string $openid = 'o_test_openid'): string
    {
        return <<<XML
<xml>
  <ToUserName><![CDATA[gh_test]]></ToUserName>
  <FromUserName><![CDATA[{$openid}]]></FromUserName>
  <CreateTime>1234567890</CreateTime>
  <MsgType><![CDATA[text]]></MsgType>
  <Content><![CDATA[{$content}]]></Content>
  <MsgId>{$msgId}</MsgId>
</xml>
XML;
    }

    /**
     * 发起微信回调请求
     */
    private function postWechat(string $xml, ?array $query = null): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            '/wechat/serve?'.http_build_query($query ?? $this->sign()),
            [], [], [],
            ['CONTENT_TYPE' => 'text/xml'],
            $xml,
        );
    }

    #[Test]
    #[TestDox('文本消息：立即返回空响应，并派发智能客服异步回复任务')]
    public function serve_dispatches_async_reply_job_for_text_message(): void
    {
        Queue::fake();

        $response = $this->postWechat($this->textMessageXml('怎么重置密码？'));

        // 被动响应为空串（success），避免微信超时重推
        $response->assertOk();
        $this->assertSame('success', $response->getContent());

        Queue::assertPushed(
            ReplyWechatTextMessageJob::class,
            fn (ReplyWechatTextMessageJob $job): bool => $job->messageId === '1234567890123456'
                && $job->openid === 'o_test_openid'
                && $job->content === '怎么重置密码？',
        );
    }

    #[Test]
    #[TestDox('关注事件：分发领域事件并返回兜底文案（不触发智能客服任务）')]
    public function serve_dispatches_event_and_returns_fallback_for_subscribe(): void
    {
        Queue::fake();
        Event::fake([WechatSubscribed::class]);

        $xml = <<<'XML'
<xml>
  <ToUserName><![CDATA[gh_test]]></ToUserName>
  <FromUserName><![CDATA[o_test_openid]]></FromUserName>
  <CreateTime>1234567890</CreateTime>
  <MsgType><![CDATA[event]]></MsgType>
  <Event><![CDATA[subscribe]]></Event>
</xml>
XML;

        $response = $this->postWechat($xml);

        $response->assertOk();
        $this->assertStringContainsString((string) __('system.wechat_fallback_busy'), $response->getContent());

        Event::assertDispatched(WechatSubscribed::class);
        Queue::assertNotPushed(ReplyWechatTextMessageJob::class);
    }

    #[Test]
    #[TestDox('签名非法时回调被拒绝')]
    public function serve_rejects_invalid_signature(): void
    {
        Queue::fake();

        $response = $this->postWechat($this->textMessageXml('你好'), [
            'signature' => 'invalid',
            'timestamp' => (string) time(),
            'nonce' => 'abc',
        ]);

        $this->assertNotSame(200, $response->getStatusCode());
        Queue::assertNotPushed(ReplyWechatTextMessageJob::class);
    }
}
