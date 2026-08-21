<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 用户性别
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
enum Gender: int implements \JsonSerializable
{
    use HasLabel;

    // 性别定义
    case GENDER_UNKNOWN = 0; // 未知、保密
    case GENDER_MALE = 1; // 男
    case GENDER_FEMALE = 2; // 女

    /**
     * 获取用户性别可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::GENDER_UNKNOWN => '保密',
            self::GENDER_MALE => '男',
            self::GENDER_FEMALE => '女',
        };
    }
}
