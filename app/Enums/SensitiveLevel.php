<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 敏感词命中级别
 */
enum SensitiveLevel: string implements \JsonSerializable
{
    use HasLabel;

    /** 低危 - 自动替换为*后放行 */
    case REPLACE = 'replace';

    /** 中危 - 内容可提交但标记为待审核 */
    case REVIEW = 'review';

    /** 高危 - 直接拦截拒绝 */
    case BLOCK = 'block';

    public function label(): string
    {
        return match ($this) {
            self::REPLACE => '自动替换',
            self::REVIEW => '人工审核',
            self::BLOCK => '直接拦截',
        };
    }

    public function labelType(): string
    {
        return match ($this) {
            self::REPLACE => 'info',
            self::REVIEW => 'warning',
            self::BLOCK => 'danger',
        };
    }

    public function isReplace(): bool
    {
        return $this === self::REPLACE;
    }

    public function isReview(): bool
    {
        return $this === self::REVIEW;
    }

    public function isBlock(): bool
    {
        return $this === self::BLOCK;
    }
}
