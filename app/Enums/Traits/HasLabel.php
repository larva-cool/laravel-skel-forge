<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Traits;

use JsonSerializable;

/**
 * 枚举值标签特征
 *
 * @implements JsonSerializable
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
trait HasLabel
{
    /**
     * 获取所有枚举的键名数组
     */
    public static function keys(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * 获取所有枚举的键值数组
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 获取所有枚举的键值对
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            if (method_exists($case, 'label')) {
                $options[$case->value] = $case->label();
            } else {
                $options[$case->value] = $case->name;
            }
        }

        return $options;
    }

    /**
     * 获取枚举值的 JSON 序列化数据
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}
