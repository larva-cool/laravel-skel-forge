<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Enums\SocialProvider;
use App\Models\System\Social;
use App\Services\WechatMiniService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 微信小程序客服消息通知通道
 *
 * 仅在用户曾主动向小程序客服会话发送过消息的 48 小时内可触达。
 * 通知类需实现 toWechatMiniProgram(mixed $notifiable) 方法，返回下列形态之一：
 *  - string：作为客服文本消息下发
 *  - array：直接作为客服消息体透传（msgtype + 业务字段，如 text/image/link/miniprogram 等）
 *
 * notifiable 通过 routeNotificationForWechatMiniProgram() 返回 openid；
 * 默认从 Social 表中按 WECHAT_MINI_PROGRAM 渠道反查第一条记录。
 */
class WechatMiniProgramChannel
{
    public function __construct(protected readonly WechatMiniService $wechat) {}

    /**
     * 发送客服消息
     *
     * @return bool 微信侧返回 errcode 为 0 时返回 true，否则 false
     */
    public function send(mixed $notifiable, Notification $notification): bool
    {
        if (! method_exists($notification, 'toWechatMiniProgram')) {
            throw new RuntimeException(sprintf(
                '通知类 %s 必须实现 toWechatMiniProgram() 方法。',
                $notification::class,
            ));
        }

        $toUser = $this->resolveOpenid($notifiable, $notification);

        if ($toUser === null || $toUser === '') {
            Log::info('小程序客服消息跳过：未找到 openid', [
                'notification' => $notification::class,
                'notifiable' => $notifiable instanceof Model
                    ? $notifiable::class.':'.$notifiable->getKey()
                    : null,
            ]);

            return false;
        }

        $payload = $notification->toWechatMiniProgram($notifiable);

        if (is_string($payload)) {
            $payload = ['msgtype' => 'text', 'text' => ['content' => $payload]];
        }

        if (! is_array($payload) || ! isset($payload['msgtype'])) {
            throw new RuntimeException(sprintf(
                '通知类 %s 的 toWechatMiniProgram() 返回值必须为 string 或包含 msgtype 的 array。',
                $notification::class,
            ));
        }

        return $this->wechat->sendMessage($toUser, $payload);
    }

    /**
     * 解析 openid
     *
     * 优先级：
     *  1. notifiable->routeNotificationForWechatMiniProgram() 返回值
     *  2. notifiable->routeNotificationFor('wechat_mini_program', $notification) 字符串返回值
     *  3. notifiable->socials 关系中 WECHAT_MINI_PROGRAM 渠道的第一条记录
     */
    protected function resolveOpenid(mixed $notifiable, Notification $notification): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForWechatMiniProgram')) {
            $openid = $notifiable->routeNotificationForWechatMiniProgram($notification);

            if (is_string($openid) && $openid !== '') {
                return $openid;
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $openid = $notifiable->routeNotificationFor('wechat_mini_program', $notification);

            if (is_string($openid) && $openid !== '') {
                return $openid;
            }
        }

        return $this->lookupOpenidFromSocials($notifiable);
    }

    /**
     * 从 socials 表反查 WECHAT_MINI_PROGRAM 渠道的 openid；任何异常都降级为 null
     */
    protected function lookupOpenidFromSocials(mixed $notifiable): ?string
    {
        if (! $notifiable instanceof Model) {
            return null;
        }

        try {
            $morphClass = $notifiable->getMorphClass();

            $social = Social::query()
                ->where('user_type', $morphClass)
                ->where('user_id', $notifiable->getKey())
                ->where('provider', SocialProvider::WECHAT_MINI_PROGRAM->value)
                ->first();

            if ($social instanceof Social && $social->openid !== '') {
                return $social->openid;
            }
        } catch (\Throwable $e) {
            Log::warning('小程序客服消息反查 socials 失败：'.$e->getMessage(), [
                'notifiable' => $notifiable::class.':'.$notifiable->getKey(),
            ]);
        }

        return null;
    }
}
