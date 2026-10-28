<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Automation;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * 自动化运营提醒通知（生日/节日/注册周年）
 *
 * 触达渠道由规则配置动态决定（database / wechat_mini_program）。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $channels  Laravel 通知通道名/类名列表
     */
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly array $channels = ['database'],
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return $this->channels;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => $this->title,
            'body' => $this->content,
            'type' => 'automation.reminder',
        ]);
    }

    /**
     * 微信小程序客服消息载体（纯文本）
     */
    public function toWechatMiniProgram(mixed $notifiable): string
    {
        return $this->title."\n\n".$this->content;
    }
}
