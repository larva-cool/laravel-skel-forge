<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Task;

use App\Enums\Traits\HasLabel;

/**
 * 任务重置类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum TaskResetType: int implements \JsonSerializable
{
    use HasLabel;

    case NONE = 0; // 不重置（一次性任务）
    case DAILY = 1; // 每日重置
    case WEEKLY = 2; // 每周重置
    case MONTHLY = 3; // 每月重置

    /**
     * 获取重置类型的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => '不重置',
            self::DAILY => '每日',
            self::WEEKLY => '每周',
            self::MONTHLY => '每月',
        };
    }
}
