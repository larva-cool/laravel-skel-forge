<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 支付渠道
 *
 * MANUAL 用于管理员后台手动确认到账；其余渠道待支付网关接入后启用
 * （见文档站（~/Skel/doc）「技术方案 · 钱包」第 12.1 节「支付渠道对接（挂起）」）。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum PayChannel: string implements \JsonSerializable
{
    use HasLabel;

    case MANUAL = 'manual'; // 后台手动确认
    case WECHAT = 'wechat'; // 微信支付
    case ALIPAY = 'alipay'; // 支付宝
    case APPLE = 'apple'; // 苹果内购

    /**
     * 获取渠道的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => '后台确认',
            self::WECHAT => '微信支付',
            self::ALIPAY => '支付宝',
            self::APPLE => '苹果内购',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::MANUAL => 'info',
            self::WECHAT => 'success',
            self::ALIPAY => 'primary',
            self::APPLE => 'warning',
        };
    }

    /**
     * 支付网关是否已接入
     *
     * 未接入的渠道禁止用户下单，避免产生无法支付的僵尸订单。
     */
    public function isAvailable(): bool
    {
        return $this === self::MANUAL;
    }

    /**
     * 获取当前可用于下单的渠道值列表
     *
     * @return array<int, string>
     */
    public static function availableValues(): array
    {
        return array_values(array_map(
            fn (self $case) => $case->value,
            array_filter(self::cases(), fn (self $case) => $case->isAvailable())
        ));
    }
}
