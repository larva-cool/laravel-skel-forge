<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 定时任务状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum ScheduleTaskStatus: int implements \JsonSerializable
{
    use HasLabel;

    case INACTIVE = 0; // 禁用
    case ACTIVE = 1; // 启用

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '启用',
            self::INACTIVE => '禁用',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'info',
        };
    }

    /**
     * 是否为启用状态
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * 是否为禁用状态
     */
    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }
}
