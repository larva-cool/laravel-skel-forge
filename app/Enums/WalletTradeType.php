<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 钱包交易类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum WalletTradeType: string implements \JsonSerializable
{
    use HasLabel;

    case RECHARGE = 'recharge'; // 充值到账
    case WITHDRAW = 'withdraw'; // 提现出账
    case FREEZE = 'freeze'; // 冻结
    case UNFREEZE = 'unfreeze'; // 解冻
    case PAYMENT = 'payment'; // 消费
    case REFUND = 'refund'; // 退款
    case ADMIN_RECHARGE = 'admin_recharge'; // 后台充正
    case ADMIN_DEDUCT = 'admin_deduct'; // 后台扣减

    /**
     * 获取交易类型的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::RECHARGE => '充值',
            self::WITHDRAW => '提现',
            self::FREEZE => '冻结',
            self::UNFREEZE => '解冻',
            self::PAYMENT => '消费',
            self::REFUND => '退款',
            self::ADMIN_RECHARGE => '后台充正',
            self::ADMIN_DEDUCT => '后台扣减',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::RECHARGE, self::REFUND, self::ADMIN_RECHARGE => 'success',
            self::WITHDRAW, self::PAYMENT, self::ADMIN_DEDUCT => 'danger',
            self::FREEZE => 'warning',
            self::UNFREEZE => 'primary',
        };
    }

    /**
     * 是否为仅变动冻结余额的类型
     *
     * 冻结、解冻与提现结算不改变资产总额在可用与冻结之间的净值语义，
     * 统计收支时需要排除，避免重复计入。
     */
    public function isFrozenOnly(): bool
    {
        return in_array($this, [self::FREEZE, self::UNFREEZE], true);
    }
}
