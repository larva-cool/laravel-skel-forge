<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Task;

use App\Enums\Traits\HasLabel;

/**
 * 任务进度状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum TaskProgressStatus: int implements \JsonSerializable
{
    use HasLabel;

    case IN_PROGRESS = 0; // 进行中
    case COMPLETED = 1; // 已完成待领取
    case REWARDED = 2; // 已领取奖励
    case EXPIRED = 3; // 已过期

    /**
     * 获取进度状态的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => '进行中',
            self::COMPLETED => '已完成',
            self::REWARDED => '已领取',
            self::EXPIRED => '已过期',
        };
    }
}
