<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * 任务条件 JSON 字段 Cast
 *
 * 自动为 condition JSON 字段提供默认值，避免每次访问都要判空。
 * 默认结构：{ serial_days: 0, target_value: 0, completeness_threshold: 100 }
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AsTaskCondition implements CastsAttributes
{
    /** @var array<string, int> 默认条件值 */
    protected const DEFAULTS = [
        'serial_days' => 0,
        'target_value' => 0,
        'completeness_threshold' => 100,
    ];

    /**
     * 从数据库读取并合并默认值
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : (array) $value;

        return array_merge(self::DEFAULTS, $decoded ?? []);
    }

    /**
     * 写入数据库时自动 JSON 编码
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|false
    {
        $merged = array_merge(self::DEFAULTS, (array) $value);

        return json_encode($merged, JSON_UNESCAPED_UNICODE);
    }
}
