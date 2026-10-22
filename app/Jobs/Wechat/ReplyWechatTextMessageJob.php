<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\Wechat;

use App\Ai\Agents\WechatAssistant;
use App\Services\WechatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 微信公众号文本消息智能回复任务
 *
 * 微信被动响应有 5 秒超时限制（超时后微信会重推同一消息），
 * 因此 AI 生成改为异步执行，回复内容通过客服消息接口下发。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReplyWechatTextMessageJob implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** 单次任务最大执行时间（含 AI 生成与客服消息下发） */
    public int $timeout = 120;

    public function __construct(
        public readonly string $messageId,
        public readonly string $openid,
        public readonly string $content,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WechatService $wechat): void
    {
        // 按 MsgId 去重：微信超时重推的同一消息只回复一次
        $dedupKey = "wechat:text-reply:{$this->messageId}";
        if ($this->messageId !== '' && ! Cache::add($dedupKey, 1, now()->addHours(24))) {
            return;
        }

        $reply = $this->generateReply();

        if (! $wechat->sendTextMessage($this->openid, $reply)) {
            Cache::forget($dedupKey);

            throw new \RuntimeException("微信客服消息发送失败（openid: {$this->openid}）");
        }
    }

    /**
     * 任务最终失败回调（达到最大重试后）
     */
    public function failed(Throwable $e): void
    {
        Log::error('微信文本消息智能回复最终失败', [
            'message_id' => $this->messageId,
            'openid' => $this->openid,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * 调用智能客服生成回复，异常或空回复时回退为兜底文案
     */
    protected function generateReply(): string
    {
        try {
            $reply = trim((string) WechatAssistant::make()->prompt($this->content, timeout: 60));
        } catch (Throwable $e) {
            Log::warning('微信智能客服生成回复异常：'.$e->getMessage(), ['exception' => $e]);
            $reply = '';
        }

        return $reply === '' ? (string) __('system.wechat_fallback_busy') : $reply;
    }
}
