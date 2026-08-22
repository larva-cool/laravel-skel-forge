<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 字典状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum DictStatus: int implements \JsonSerializable
{
    use HasLabel;

    case DISABLED = 0; // 禁用
    case ENABLED = 1; // 启用

    /**
     * 获取字典状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ENABLED => '启用',
            self::DISABLED => '禁用',
        };
    }

    /**
     * 是否为启用状态
     */
    public function isEnabled(): bool
    {
        return $this === self::ENABLED;
    }

    /**
     * 是否为禁用状态
     */
    public function isDisabled(): bool
    {
        return $this === self::DISABLED;
    }
}
