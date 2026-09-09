<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 提现申请状态
 *
 * 状态流转：
 *   pending ──(审核通过)──> approved
 *   pending ──(审核驳回)──> rejected（余额解冻退回）
 *   approved ──(打款成功)──> success
 *   approved ──(打款失败)──> failed（余额解冻退回）
 *
 * rejected / success / failed 为终态。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum WithdrawStatus: string implements \JsonSerializable
{
    use HasLabel;

    case PENDING = 'pending'; // 待审核
    case APPROVED = 'approved'; // 审核通过
    case REJECTED = 'rejected'; // 已驳回
    case SUCCESS = 'success'; // 打款成功
    case FAILED = 'failed'; // 打款失败

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待审核',
            self::APPROVED => '已通过',
            self::REJECTED => '已驳回',
            self::SUCCESS => '打款成功',
            self::FAILED => '打款失败',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'primary',
            self::REJECTED => 'danger',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
        };
    }

    /**
     * 是否待审核
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 是否已审核通过（待打款）
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * 是否为终态
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::REJECTED, self::SUCCESS, self::FAILED], true);
    }
}
