<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Fluent;

/**
 * 为模型添加设置属性支持。
 *
 * 该 trait 提供了模型 settings 字段的读写能力，支持以 Fluent 对象方式访问设置项，
 * 并可通过定义 `DEFAULT_SETTINGS` 常量来设置默认值，存储的设置会与默认值进行递归合并。
 *
 * @mixin Model
 *
 * @property Fluent $settings
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasSettingsProperty
{
    /**
     * 设置 settings 属性，将数组序列化为 JSON 存储。
     *
     * @param  array<string,mixed>  $settings
     */
    public function setSettingsAttribute(array $settings): void
    {
        $this->attributes['settings'] = json_encode($settings);
    }

    /**
     * 获取 settings 属性，返回 Fluent 对象以支持动态属性访问。
     */
    public function getSettingsAttribute(): Fluent
    {
        return new Fluent($this->getSettings());
    }

    /**
     * 获取合并后的设置数组。
     *
     * 将 `DEFAULT_SETTINGS` 常量定义的默认值与数据库中存储的设置进行递归合并，
     * 存储的设置优先级高于默认值。
     *
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        return \array_replace_recursive(\defined('static::DEFAULT_SETTINGS') ? \constant('static::DEFAULT_SETTINGS') : [], \json_decode($this->attributes['settings'] ?? '{}', true) ?? []);
    }
}
