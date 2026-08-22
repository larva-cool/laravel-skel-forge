<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 反馈状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum FeedbackStatus: int implements \JsonSerializable
{
    use HasLabel;

    case PENDING = 0; // 待处理
    case ANALYZING = 1; // 分析中
    case PLANNED = 2; // 已规划
    case PROCESSING = 3; // 处理中
    case RESOLVED = 4; // 已解决
    case CLOSED = 5; // 已关闭

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::ANALYZING => '分析中',
            self::PLANNED => '已规划',
            self::PROCESSING => '处理中',
            self::RESOLVED => '已解决',
            self::CLOSED => '已关闭',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::PENDING => 'info',
            self::ANALYZING => 'warning',
            self::PLANNED => 'primary',
            self::PROCESSING => 'warning',
            self::RESOLVED => 'success',
            self::CLOSED => 'info',
        };
    }

    /**
     * 是否为待推进状态（未闭环）
     */
    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::ANALYZING, self::PLANNED, self::PROCESSING], true);
    }

    /**
     * 是否已闭环
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::RESOLVED, self::CLOSED], true);
    }
}
