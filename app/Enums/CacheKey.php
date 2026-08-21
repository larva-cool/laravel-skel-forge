<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

/**
 * 缓存 Key 常量
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CacheKey
{
    public const string SETTINGS = 'system:settings'; // 系统配置缓存 Key
    public const string DICT_TYPE = 'system:dicts:%s'; // 数据字典类型缓存 Key
    public const string AREA_TREE = 'system:areas:%s'; // 地区缓存

    /**
     * 生成数据字典类型缓存 Key
     */
    public static function key(string $key, mixed ...$values): string
    {
        return sprintf($key, ...$values);
    }
}
