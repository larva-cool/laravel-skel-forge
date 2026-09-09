<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 充值订单支付状态
 *
 * 状态流转：
 *   pending ──(确认到账)──> success
 *   pending ──(超时任务)──> expired
 *   pending ──(用户/管理员取消)──> closed
 *   pending ──(渠道支付失败)──> failed
 *
 * success 为终态，不可再次流转（幂等保障）。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum RechargeStatus: string implements \JsonSerializable
{
    use HasLabel;

    case PENDING = 'pending'; // 待支付
    case SUCCESS = 'success'; // 已到账
    case FAILED = 'failed'; // 支付失败
    case EXPIRED = 'expired'; // 已过期
    case CLOSED = 'closed'; // 已关闭

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待支付',
            self::SUCCESS => '已到账',
            self::FAILED => '支付失败',
            self::EXPIRED => '已过期',
            self::CLOSED => '已关闭',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SUCCESS => 'success',
            self::FAILED, self::EXPIRED => 'danger',
            self::CLOSED => 'info',
        };
    }

    /**
     * 是否为待支付状态
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 是否已到账
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * 是否为终态（不可再流转）
     */
    public function isFinal(): bool
    {
        return $this !== self::PENDING;
    }
}
