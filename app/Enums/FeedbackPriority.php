<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 反馈优先级枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum FeedbackPriority: int implements \JsonSerializable
{
    use HasLabel;

    case LOW = 1; // 低
    case MEDIUM = 2; // 中
    case HIGH = 3; // 高
    case URGENT = 4; // 紧急

    /**
     * 获取优先级标签
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => '低',
            self::MEDIUM => '中',
            self::HIGH => '高',
            self::URGENT => '紧急',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::LOW => 'info',
            self::MEDIUM => 'warning',
            self::HIGH => 'danger',
            self::URGENT => 'danger',
        };
    }
}
