<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 金币交易类型
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
enum CoinType: string implements \JsonSerializable
{
    use HasLabel;

    // 交易类型常量定义
    case TYPE_UNKNOWN = 'unknown_type'; // 未知类型
    case TYPE_SIGN_IN = 'sign_in'; // 签到获取金币
    case TYPE_INVITE_REGISTER = 'invite_register'; // 邀请注册获取金币
    case TYPE_TASK = 'task'; // 任务
    case TYPE_TRANS = 'trans'; // 交易
    case TYPE_ADMIN_RECHARGE = 'admin_recharge'; // 后台充值
    case TYPE_ADMIN_DEDUCT = 'admin_deduct'; // 后台扣减

    /**
     * 获取金币交易类型的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::TYPE_UNKNOWN => '未知',
            self::TYPE_SIGN_IN => '签到',
            self::TYPE_INVITE_REGISTER => '邀请注册',
            self::TYPE_TASK => '任务',
            self::TYPE_TRANS => '交易',
            self::TYPE_ADMIN_RECHARGE => '后台充值',
            self::TYPE_ADMIN_DEDUCT => '后台扣减',
        };
    }
}
