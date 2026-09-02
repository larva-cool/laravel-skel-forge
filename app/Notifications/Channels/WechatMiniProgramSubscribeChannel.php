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
 * 微信小程序订阅消息通知通道
 *
 * 通知类需实现 toWechatMiniProgramSubscribe(mixed $notifiable) 方法，返回下列形态之一：
 *  - string template_id：仅传模板 ID，data/page/miniprogram_state 由通知类承担
 *  - array：
 *      - template_id  必填
 *      - data         必填，形如 ['key1' => ['value' => '...'], ...]
 *      - page         可选，点击卡片跳转的小程序页面（含参数）
 *      - miniprogram_state 可选 developer|trial|formal，默认 formal
 *
 * notifiable 通过 routeNotificationForWechatMiniProgramSubscribe() 返回 openid；
 * 默认从 Social 表中按 WECHAT_MINI_PROGRAM 渠道反查第一条记录。
 */
class WechatMiniProgramSubscribeChannel
{
    public function __construct(protected readonly WechatMiniService $wechat) {}

    /**
     * 发送订阅消息
     *
     * @return bool 微信侧返回 errcode 为 0 时返回 true，否则 false
     */
    public function send(mixed $notifiable, Notification $notification): bool
    {
        if (! method_exists($notification, 'toWechatMiniProgramSubscribe')) {
            throw new RuntimeException(sprintf(
                '通知类 %s 必须实现 toWechatMiniProgramSubscribe() 方法。',
                $notification::class,
            ));
        }

        $toUser = $this->resolveOpenid($notifiable, $notification);

        if ($toUser === null || $toUser === '') {
            Log::info('小程序订阅消息跳过：未找到 openid', [
                'notification' => $notification::class,
                'notifiable' => $notifiable instanceof Model
                    ? $notifiable::class.':'.$notifiable->getKey()
                    : null,
            ]);

            return false;
        }

        $payload = $notification->toWechatMiniProgramSubscribe($notifiable);

        $params = is_string($payload)
            ? ['template_id' => $payload]
            : $this->normalizePayload($payload, $notification);

        $params['touser'] = $toUser;

        return $this->wechat->sendTemplateMessage($params);
    }

    /**
     * 解析 openid
     *
     * 优先级：
     *  1. notifiable->routeNotificationForWechatMiniProgramSubscribe() 返回值
     *  2. notifiable->routeNotificationFor('wechat_mini_program_subscribe', $notification) 字符串返回值
     *  3. notifiable->socials() 关系中 WECHAT_MINI_PROGRAM 渠道的第一条记录
     */
    protected function resolveOpenid(mixed $notifiable, Notification $notification): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForWechatMiniProgramSubscribe')) {
            $openid = $notifiable->routeNotificationForWechatMiniProgramSubscribe($notification);

            if (is_string($openid) && $openid !== '') {
                return $openid;
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $openid = $notifiable->routeNotificationFor('wechat_mini_program_subscribe', $notification);

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
            Log::warning('小程序订阅消息反查 socials 失败：'.$e->getMessage(), [
                'notifiable' => $notifiable::class.':'.$notifiable->getKey(),
            ]);
        }

        return null;
    }

    /**
     * 规整化 array 形态的订阅消息载荷
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $payload, Notification $notification): array
    {
        $templateId = (string) ($payload['template_id'] ?? '');

        if ($templateId === '') {
            throw new RuntimeException(sprintf(
                '通知类 %s 的 toWechatMiniProgramSubscribe() 缺少 template_id。',
                $notification::class,
            ));
        }

        $data = $payload['data'] ?? [];

        if (! is_array($data) || $data === []) {
            throw new RuntimeException(sprintf(
                '通知类 %s 的 toWechatMiniProgramSubscribe() 缺少 data 字段。',
                $notification::class,
            ));
        }

        // 微信 data 形如 ['key1' => ['value' => 'any']]，调用方传 ['key1' => 'any'] 时自动包一层 value
        $data = $this->normalizeData($data);

        $params = [
            'template_id' => $templateId,
            'data' => $data,
        ];

        if (! empty($payload['page'])) {
            $params['page'] = (string) $payload['page'];
        }

        $state = $payload['miniprogram_state'] ?? null;
        if (is_string($state) && in_array($state, ['developer', 'trial', 'formal'], true)) {
            $params['miniprogram_state'] = $state;
        }

        return $params;
    }

    /**
     * 将 ['k' => 'v'] 自动包成 ['k' => ['value' => 'v']]
     *
     * @param  array<int|string, mixed>  $data
     * @return array<string, array{value: mixed}>
     */
    protected function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && array_key_exists('value', $value)) {
                $normalized[(string) $key] = $value;
            } else {
                $normalized[(string) $key] = ['value' => $value];
            }
        }

        return $normalized;
    }
}
