<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 举报处理状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum ReportStatus: string implements \JsonSerializable
{
    use HasLabel;

    case PENDING = 'pending';     // 待处理
    case PROCESSING = 'processing'; // 处理中
    case RESOLVED = 'resolved';   // 已处理
    case REJECTED = 'rejected';   // 已驳回

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::PROCESSING => '处理中',
            self::RESOLVED => '已处理',
            self::REJECTED => '已驳回',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'primary',
            self::RESOLVED => 'success',
            self::REJECTED => 'info',
        };
    }

    /**
     * 是否为待处理状态
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 是否为处理中状态
     */
    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * 是否已处理
     */
    public function isResolved(): bool
    {
        return $this === self::RESOLVED;
    }

    /**
     * 是否已驳回
     */
    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    /**
     * 是否已结案（已处理/已驳回）
     */
    public function isClosed(): bool
    {
        return $this === self::RESOLVED || $this === self::REJECTED;
    }
}
