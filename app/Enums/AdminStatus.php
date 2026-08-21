<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 管理员状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AdminStatus: int implements \JsonSerializable
{
    use HasLabel;

    case DISABLED = 0; // 禁用
    case ACTIVE = 1; // 正常

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DISABLED => '禁用',
            self::ACTIVE => '正常',
        };
    }

    /**
     * 是否为正常状态
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * 是否为禁用状态
     */
    public function isDisabled(): bool
    {
        return $this === self::DISABLED;
    }
}
