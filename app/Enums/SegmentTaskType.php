<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 分群异步任务类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum SegmentTaskType: string implements \JsonSerializable
{
    use HasLabel;

    case ATTACH = 'attach';   // 批量打标
    case DETACH = 'detach';   // 批量解标

    public function label(): string
    {
        return match ($this) {
            self::ATTACH => '批量打标',
            self::DETACH => '批量解标',
        };
    }
}
