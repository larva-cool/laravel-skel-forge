<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 提现收款账户类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum WithdrawAccountType: string implements \JsonSerializable
{
    use HasLabel;

    case WECHAT = 'wechat'; // 微信
    case ALIPAY = 'alipay'; // 支付宝
    case BANK = 'bank'; // 银行卡

    /**
     * 获取账户类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::WECHAT => '微信',
            self::ALIPAY => '支付宝',
            self::BANK => '银行卡',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::WECHAT => 'success',
            self::ALIPAY => 'primary',
            self::BANK => 'info',
        };
    }
}
