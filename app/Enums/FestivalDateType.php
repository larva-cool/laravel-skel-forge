<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 节日日期类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum FestivalDateType: string implements \JsonSerializable
{
    use HasLabel;

    case SOLAR = 'solar'; // 公历
    case LUNAR = 'lunar'; // 农历

    /**
     * 获取类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::SOLAR => '公历',
            self::LUNAR => '农历',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::SOLAR => 'primary',
            self::LUNAR => 'warning',
        };
    }
}
