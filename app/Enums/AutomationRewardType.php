<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 自动化运营奖励类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AutomationRewardType: string implements \JsonSerializable
{
    use HasLabel;

    case NONE = 'none'; // 无奖励
    case POINT = 'point'; // 积分
    case COIN = 'coin'; // 金币

    /**
     * 获取类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => '无奖励',
            self::POINT => '积分',
            self::COIN => '金币',
        };
    }
}
