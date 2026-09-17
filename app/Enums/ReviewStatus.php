<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 审核状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum ReviewStatus: string implements \JsonSerializable
{
    use HasLabel;

    case UNSUBMITTED = 'unsubmitted'; // 未提交
    case PENDING = 'pending'; // 待审核
    case REVIEW = 'review'; // 需复审
    case APPROVED = 'approved'; // 已通过
    case REJECTED = 'rejected'; // 已驳回

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::UNSUBMITTED => '未提交',
            self::PENDING => '待审核',
            self::REVIEW => '需复审',
            self::APPROVED => '已通过',
            self::REJECTED => '已驳回',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::UNSUBMITTED => 'info',
            self::PENDING => 'warning',
            self::REVIEW => 'primary',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    /**
     * 是否为未提交状态
     */
    public function isUnsubmitted(): bool
    {
        return $this === self::UNSUBMITTED;
    }

    /**
     * 是否为待审核状态
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 是否为审核中状态
     */
    public function isReview(): bool
    {
        return $this === self::REVIEW;
    }

    /**
     * 是否已通过审核
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * 是否已驳回
     */
    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }
}
