<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 敏感词分类
 */
enum SensitiveCategory: string implements \JsonSerializable
{
    use HasLabel;

    case POLITICAL = 'political';     // 涉政
    case PORNOGRAPHY = 'pornography'; // 涉黄
    case VIOLENCE = 'violence';       // 暴力
    case AD = 'ad';                   // 广告/营销
    case INSULT = 'insult';           // 辱骂
    case ILLEGAL = 'illegal';         // 违法
    case OTHER = 'other';             // 其他

    public function label(): string
    {
        return match ($this) {
            self::POLITICAL => '涉政',
            self::PORNOGRAPHY => '涉黄',
            self::VIOLENCE => '暴力',
            self::AD => '广告',
            self::INSULT => '辱骂',
            self::ILLEGAL => '违法',
            self::OTHER => '其他',
        };
    }

    public function labelType(): string
    {
        return match ($this) {
            self::POLITICAL, self::ILLEGAL => 'danger',
            self::PORNOGRAPHY, self::VIOLENCE => 'warning',
            self::AD, self::INSULT => 'info',
            self::OTHER => '',
        };
    }
}
