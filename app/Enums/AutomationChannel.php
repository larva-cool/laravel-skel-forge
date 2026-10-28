<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;
use App\Notifications\Channels\WechatMiniProgramChannel;

/**
 * 自动化运营触达渠道枚举
 *
 * value 与 Laravel 通知通道名保持一致：
 *  - database：站内通知（内置数据库通道）
 *  - wechat_mini_program：微信小程序客服消息（自定义通道）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AutomationChannel: string implements \JsonSerializable
{
    use HasLabel;

    case DATABASE = 'database'; // 站内通知
    case WECHAT_MINI_PROGRAM = 'wechat_mini_program'; // 微信小程序客服消息

    /**
     * 获取渠道标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DATABASE => '站内通知',
            self::WECHAT_MINI_PROGRAM => '微信小程序',
        };
    }

    /**
     * 转换为 Laravel 通知通道名/类名
     */
    public function toChannel(): string
    {
        return match ($this) {
            self::DATABASE => 'database',
            self::WECHAT_MINI_PROGRAM => WechatMiniProgramChannel::class,
        };
    }
}
