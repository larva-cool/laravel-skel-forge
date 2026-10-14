<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Events\Wechat;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 微信扫描二维码事件
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WechatScan implements ShouldHandleEventsAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * The message.
     */
    public array $message;

    /**
     * Create a new event instance.
     */
    public function __construct(array $message)
    {
        $this->message = $message;
    }
}
