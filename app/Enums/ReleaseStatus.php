<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * App 版本发布状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum ReleaseStatus: string implements \JsonSerializable
{
    use HasLabel;

    case DRAFT = 'draft'; // 草稿
    case PUBLISHED = 'published'; // 已发布
    case WITHDRAWN = 'withdrawn'; // 已撤回
    case OBSOLETE = 'obsolete'; // 已废弃

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '草稿',
            self::PUBLISHED => '已发布',
            self::WITHDRAWN => '已撤回',
            self::OBSOLETE => '已废弃',
        };
    }

    /**
     * 是否为草稿状态
     */
    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * 是否为已发布状态
     */
    public function isPublished(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * 是否为已撤回状态
     */
    public function isWithdrawn(): bool
    {
        return $this === self::WITHDRAWN;
    }

    /**
     * 是否为已废弃状态
     */
    public function isObsolete(): bool
    {
        return $this === self::OBSOLETE;
    }
}
