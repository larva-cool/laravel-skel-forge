<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Task;

use App\Enums\Traits\HasLabel;

/**
 * 任务领取方式
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum TaskClaimMode: int implements \JsonSerializable
{
    use HasLabel;

    case AUTO = 0; // 自动发放
    case MANUAL = 1; // 手动领取

    /**
     * 获取领取方式的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::AUTO => '自动发放',
            self::MANUAL => '手动领取',
        };
    }
}
