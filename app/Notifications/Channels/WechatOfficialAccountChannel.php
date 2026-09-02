<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Enums\SocialProvider;
use App\Models\System\Social;
use App\Services\WechatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 微信公众号通知通道
 *
 * 通知类需实现 toWechatOfficialAccount(mixed $notifiable) 方法，返回下列形态之一：
 *  - string：作为客服文本消息下发
 *  - array 含 'template_id'：作为模板消息下发（其他字段：data、url、miniprogram）
 *  - array 含 'news' 键：作为图文客服消息下发
 *  - 其他 array：直接作为客服消息体透传（需自带 msgtype/xxx 等字段）
 *
 * notifiable 通过 routeNotificationForWechatOfficialAccount() 返回 openid；
 * 默认实现会从 Social 表中按 WECHAT_MP 渠道反查第一个 openid。
 */
class WechatOfficialAccountChannel
{
    public function __construct(protected readonly WechatService $wechat) {}

    /**
     * 发送通知
     *
     * @return array<string, mixed>|null 微信侧返回的响应标识，失败或跳过时返回 null
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toWechatOfficialAccount')) {
            throw new RuntimeException(sprintf(
                '通知类 %s 必须实现 toWechatOfficialAccount() 方法。',
                $notification::class,
            ));
        }

        $toUser = $this->resolveOpenid($notifiable, $notification);

        if ($toUser === null || $toUser === '') {
            Log::info('微信公众号通知跳过：未找到 openid', [
                'notification' => $notification::class,
                'notifiable' => $notifiable instanceof Model
                    ? $notifiable::class.':'.$notifiable->getKey()
                    : null,
            ]);

            return null;
        }

        $payload = $notification->toWechatOfficialAccount($notifiable);

        if (is_string($payload)) {
            return $this->sendText($toUser, $payload) ? ['msgtype' => 'text'] : null;
        }

        if (! is_array($payload)) {
            throw new RuntimeException(sprintf(
                '通知类 %s 的 toWechatOfficialAccount() 必须返回 string 或 array。',
                $notification::class,
            ));
        }

        if (isset($payload['template_id'])) {
            return $this->sendTemplate($toUser, $payload) ? ['msgtype' => 'template'] : null;
        }

        if (isset($payload['news']) && is_array($payload['news'])) {
            return $this->sendNews($toUser, $payload['news']) ? ['msgtype' => 'news'] : null;
        }

        return $this->wechat->sendMessage($toUser, $payload) ? $payload : null;
    }

    /**
     * 解析 openid
     *
     * 优先级：
     *  1. notifiable->routeNotificationForWechatOfficialAccount() 返回值
     *  2. notifiable->routeNotificationFor('wechat_official_account', $notification) 字符串返回值
     *  3. notifiable->socials() 关系中 WECHAT_MP 渠道的第一条记录
     */
    protected function resolveOpenid(mixed $notifiable, Notification $notification): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForWechatOfficialAccount')) {
            $openid = $notifiable->routeNotificationForWechatOfficialAccount($notification);

            if (is_string($openid) && $openid !== '') {
                return $openid;
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $openid = $notifiable->routeNotificationFor('wechat_official_account', $notification);

            if (is_string($openid) && $openid !== '') {
                return $openid;
            }
        }

        if ($notifiable instanceof Model) {
            $openid = $this->lookupOpenidFromSocials($notifiable);

            if (is_string($openid) && $openid !== '') {
                return $openid;
            }
        }

        return null;
    }

    /**
     * 从 socials 表反查 openid；任何异常（morph 未注册、表不存在）都降级为 null
     */
    protected function lookupOpenidFromSocials(Model $notifiable): ?string
    {
        try {
            $morphClass = $notifiable->getMorphClass();

            $social = Social::query()
                ->where('user_type', $morphClass)
                ->where('user_id', $notifiable->getKey())
                ->where('provider', SocialProvider::WECHAT_MP->value)
                ->first();

            if ($social instanceof Social) {
                return $social->openid !== '' ? $social->openid : null;
            }
        } catch (\Throwable $e) {
            Log::warning('微信公众号通知反查 socials 失败：'.$e->getMessage(), [
                'notifiable' => $notifiable::class.':'.$notifiable->getKey(),
            ]);
        }

        return null;
    }

    /**
     * 发送模板消息
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sendTemplate(string $toUser, array $payload): bool
    {
        $templateId = (string) ($payload['template_id'] ?? '');

        if ($templateId === '') {
            throw new RuntimeException('模板消息缺少 template_id 字段。');
        }

        $params = [
            'touser' => $toUser,
            'template_id' => $templateId,
            'data' => $payload['data'] ?? [],
        ];

        if (! empty($payload['url'])) {
            $params['url'] = $payload['url'];
        }

        if (! empty($payload['miniprogram']) && is_array($payload['miniprogram'])) {
            $params['miniprogram'] = $payload['miniprogram'];
        }

        return $this->wechat->sendTemplateMessage($params);
    }

    /**
     * 发送图文客服消息
     *
     * @param  array<string, mixed>  $news  图文消息体，含 articles 数组
     */
    protected function sendNews(string $toUser, array $news): bool
    {
        return $this->wechat->sendMessage($toUser, [
            'msgtype' => 'news',
            'news' => $news,
        ]);
    }

    /**
     * 发送客服文本消息
     */
    protected function sendText(string $toUser, string $content): bool
    {
        return $this->wechat->sendTextMessage($toUser, $content);
    }
}
