<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 用户状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum UserStatus: int implements \JsonSerializable
{
    use HasLabel;

    case FROZEN = 0; // 已冻结
    case ACTIVE = 1; // 正常

    /**
     * 获取用户状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '正常',
            self::FROZEN => '已冻结',
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
     * 是否为已冻结状态
     */
    public function isFrozen(): bool
    {
        return $this === self::FROZEN;
    }
}
