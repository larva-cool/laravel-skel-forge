<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 分群异步任务状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum SegmentTaskStatus: string implements \JsonSerializable
{
    use HasLabel;

    case PENDING = 'pending';     // 已入队待执行
    case RUNNING = 'running';     // 正在执行
    case COMPLETED = 'completed'; // 已完成
    case FAILED = 'failed';       // 失败

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待执行',
            self::RUNNING => '执行中',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }
}
