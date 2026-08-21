<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 调度任务执行状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum ScheduleStatus: int implements \JsonSerializable
{
    use HasLabel;

    case RUNNING = 0; // 执行中
    case SUCCESS = 1; // 成功
    case FAILED = 2;  // 失败
    case SKIPPED = 3; // 跳过

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::RUNNING => '执行中',
            self::SUCCESS => '成功',
            self::FAILED => '失败',
            self::SKIPPED => '跳过',
        };
    }

    /**
     * 是否执行中
     */
    public function isRunning(): bool
    {
        return $this === self::RUNNING;
    }

    /**
     * 是否执行成功
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * 是否执行失败
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * 是否被跳过
     */
    public function isSkipped(): bool
    {
        return $this === self::SKIPPED;
    }
}
