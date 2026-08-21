<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

/**
 * 系统配置值类型枚举类
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class SettingType
{
    /** @var string 整型 */
    public const string CAST_TYPE_INT = 'int';

    /** @var string 浮点型 */
    public const string CAST_TYPE_FLOAT = 'float';

    /** @var string 布尔型 */
    public const string CAST_TYPE_BOOL = 'bool';

    /** @var string 字符串型 */
    public const string CAST_TYPE_STRING = 'string';
}
