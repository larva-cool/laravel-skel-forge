<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Jobs\Wechat;

use App\Ai\Agents\WechatAssistant;
use App\Jobs\Wechat\ReplyWechatTextMessageJob;
use App\Services\WechatService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 微信公众号文本消息智能回复任务测试
 */
#[CoversClass(ReplyWechatTextMessageJob::class)]
#[Group('jobs')]
#[Group('wechat')]
class ReplyWechatTextMessageJobTest extends TestCase
{
    /**
     * 创建拦截客服消息发送的服务桩
     *
     * @param  bool  $succeed  发送是否成功
     * @param  int  $times  预期调用次数
     * @param  string|null  $expectedContent  预期发送的内容（null 不校验）
     */
    private function fakeWechatService(bool $succeed = true, int $times = 1, ?string $expectedContent = null): WechatService
    {
        $wechat = Mockery::mock(WechatService::class);
        $wechat->shouldReceive('sendTextMessage')
            ->times($times)
            ->with('o_test_openid', Mockery::on(fn (string $content): bool => $expectedContent === null || $content === $expectedContent))
            ->andReturn($succeed);

        return $wechat;
    }

    #[Test]
    #[TestDox('正常流程：调用智能客服生成回复并通过客服消息接口下发')]
    public function handle_generates_reply_and_sends_custom_message(): void
    {
        WechatAssistant::fake(['密码可在「我的-设置」中重置。']);

        (new ReplyWechatTextMessageJob('msg_1', 'o_test_openid', '怎么重置密码？'))
            ->handle($this->fakeWechatService(expectedContent: '密码可在「我的-设置」中重置。'));

        WechatAssistant::assertPrompted('怎么重置密码？');
    }

    #[Test]
    #[TestDox('相同 MsgId 的微信重推消息只回复一次')]
    public function handle_deduplicates_by_message_id(): void
    {
        WechatAssistant::fake(['回复内容']);

        $job = new ReplyWechatTextMessageJob('msg_dup', 'o_test_openid', '你好');
        $job->handle($this->fakeWechatService());

        // 微信超时重推同一消息：直接返回，不再生成与发送
        (new ReplyWechatTextMessageJob('msg_dup', 'o_test_openid', '你好'))
            ->handle($this->fakeWechatService(times: 0));
    }

    #[Test]
    #[TestDox('客服消息发送失败时抛出异常并清除去重标记以便重试')]
    public function handle_throws_and_clears_dedup_when_send_fails(): void
    {
        WechatAssistant::fake(['回复内容']);

        try {
            (new ReplyWechatTextMessageJob('msg_fail', 'o_test_openid', '你好'))
                ->handle($this->fakeWechatService(succeed: false));
            $this->fail('预期抛出 RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('客服消息发送失败', $e->getMessage());
        }

        // 去重标记已清除，重试可正常发送
        (new ReplyWechatTextMessageJob('msg_fail', 'o_test_openid', '你好'))
            ->handle($this->fakeWechatService());
    }

    #[Test]
    #[TestDox('AI 生成异常时回退为兜底文案并正常下发')]
    public function handle_falls_back_to_busy_message_when_ai_fails(): void
    {
        WechatAssistant::fake(fn () => throw new \RuntimeException('AI 网关不可用'));

        (new ReplyWechatTextMessageJob('msg_ai_fail', 'o_test_openid', '你好'))
            ->handle($this->fakeWechatService(expectedContent: (string) __('system.wechat_fallback_busy')));
    }

    #[Test]
    #[TestDox('AI 返回空内容时回退为兜底文案并正常下发')]
    public function handle_falls_back_to_busy_message_when_ai_returns_empty(): void
    {
        WechatAssistant::fake(['   ']);

        (new ReplyWechatTextMessageJob('msg_empty', 'o_test_openid', '你好'))
            ->handle($this->fakeWechatService(expectedContent: (string) __('system.wechat_fallback_busy')));
    }

    #[Test]
    #[TestDox('MsgId 为空时跳过去重直接处理')]
    public function handle_skips_dedup_when_message_id_is_empty(): void
    {
        WechatAssistant::fake(['回复内容']);

        $job = new ReplyWechatTextMessageJob('', 'o_test_openid', '你好');
        $job->handle($this->fakeWechatService());
        $job->handle($this->fakeWechatService());

        $this->assertFalse(Cache::has('wechat:text-reply:'));
    }
}
