<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 用户分群类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum SegmentType: int implements \JsonSerializable
{
    use HasLabel;

    case MANUAL = 1; // 手动分群（管理员维护成员）
    case RULE = 2;   // 规则分群（定时任务按规则自动同步成员）

    /**
     * 获取分群类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => '手动',
            self::RULE => '规则',
        };
    }

    /**
     * 是否为规则分群
     */
    public function isRule(): bool
    {
        return $this === self::RULE;
    }

    /**
     * 是否为手动分群
     */
    public function isManual(): bool
    {
        return $this === self::MANUAL;
    }
}
