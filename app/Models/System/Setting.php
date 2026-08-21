<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Support\Carbon;

/**
 * 配置模型
 *
 * @property int $id 配置ID
 * @property string $name 配置名称
 * @property string $key 配置 Key
 * @property string $value 配置值
 * @property string $cast_type 配置变量类型
 * @property string $input_type 配置输入类型
 * @property string $param 配置参数
 * @property int $sort 排序权重，越小越靠前
 * @property string $remark 配置描述
 * @property Carbon $updated_at 配置更新时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('settings')]
#[Fillable(['name', 'key', 'value', 'cast_type', 'input_type', 'param', 'sort', 'remark'])]
class Setting extends Model
{
    /**
     * "created at"列的名称。
     *
     * @var string|null
     */
    public const CREATED_AT = null;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'sort' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'key' => 'string',
            'value' => 'string',
            'cast_type' => 'string',
            'input_type' => 'string',
            'param' => 'string',
            'sort' => 'integer',
            'remark' => 'string',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 获取配置变量类型
     *
     * @param  string  $key  配置 Key
     * @param  string  $default  默认值
     */
    public static function getValueType(string $key, string $default = 'string'): string
    {
        return self::query()->where('key', $key)->value('cast_type') ?? $default;
    }

    /**
     * 获取所有配置
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        $settings = [];
        self::query()->orderBy('sort')->get()->each(function ($setting) use (&$settings) {
            $settings[$setting['key']] = $setting['value'];
        });

        return $settings;
    }

    /**
     * 批量设置配置
     *
     * @param  array  $data  配置 Key 数组
     */
    public static function batchSet(array $data): void
    {
        $updateTime = Carbon::now();
        $items = [];
        foreach ($data as $item) {
            $item = array_merge([
                'name' => null,
                'key' => null,
                'value' => null,
                'cast_type' => 'string',
                'input_type' => 'text',
                'param' => null,
                'sort' => 0,
                'remark' => null,
            ], $item);

            $item['updated_at'] = $updateTime;
            $items[] = $item;
        }

        self::query()->upsert(
            $items,
            ['key'],
            ['name', 'value', 'cast_type', 'input_type', 'param', 'sort', 'remark', 'updated_at'],
        );
    }
}
