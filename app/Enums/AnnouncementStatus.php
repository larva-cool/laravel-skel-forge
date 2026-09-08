<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 公告状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AnnouncementStatus: string implements \JsonSerializable
{
    use HasLabel;

    case DRAFT = 'draft'; // 草稿
    case PUBLISHED = 'published'; // 已发布
    case ARCHIVED = 'archived'; // 已归档

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '草稿',
            self::PUBLISHED => '已发布',
            self::ARCHIVED => '已归档',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::DRAFT => 'info',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'warning',
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
     * 是否为已归档状态
     */
    public function isArchived(): bool
    {
        return $this === self::ARCHIVED;
    }
}
