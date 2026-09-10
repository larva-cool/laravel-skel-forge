<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Task;

use App\Enums\Traits\HasLabel;

/**
 * 任务奖励类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum TaskRewardType: string implements \JsonSerializable
{
    use HasLabel;

    case COIN = 'coin'; // 金币
    case POINT = 'point'; // 积分
    case VIP_DAYS = 'vip_days'; // VIP天数

    /**
     * 获取奖励类型的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::COIN => '金币',
            self::POINT => '积分',
            self::VIP_DAYS => 'VIP天数',
        };
    }
}
