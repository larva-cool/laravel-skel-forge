<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * App 平台枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AppPlatform: string implements \JsonSerializable
{
    use HasLabel;

    case ANDROID = 'android'; // 安卓
    case IOS = 'ios'; // iOS
    case HARMONY = 'harmony'; // 鸿蒙

    /**
     * 获取平台标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ANDROID => '安卓',
            self::IOS => 'iOS',
            self::HARMONY => '鸿蒙',
        };
    }
}
