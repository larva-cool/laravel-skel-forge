<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\CacheKey;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 字典类型模型
 *
 * @property int $id 字典ID
 * @property string $name 字典名称
 * @property string $code 字典编码
 * @property int $status 状态：1启用 0禁用
 * @property int $sort 排序权重，越小越靠前
 * @property string|null $remark 备注
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系模型：
 * @property Collection<int, DictItem> $dictItems 字典数据
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('dicts')]
#[Fillable(['name', 'code', 'status', 'sort', 'remark'])]
class Dict extends Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 1,
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
            'code' => 'string',
            'status' => 'integer',
            'sort' => 'integer',
            'remark' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::deleted(function (Dict $dict) {
            $dict->dictItems()->delete();
            Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, $dict->code));
        });

        // 字典本身更新时也清除缓存（例如状态变更）
        static::saved(function (Dict $dict) {
            Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, $dict->code));
        });
    }

    /**
     * 字典数据
     */
    public function dictItems(): HasMany
    {
        return $this->hasMany(DictItem::class, 'dict_id', 'id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * 根据字典编码获取启用的字典项（带缓存）
     *
     * 缓存纯数组而非 Eloquent Collection，避免 Octane/反序列化场景下
     * 出现 "incomplete object" 错误；读取时再 hydrate 为模型集合。
     *
     * @return Collection<int, DictItem>
     */
    public static function getActiveItemsByCode(string $code): Collection
    {
        $cacheKey = CacheKey::key(CacheKey::DICT_TYPE, $code);

        /** @var array<int, array<string, mixed>> $itemsArray */
        $itemsArray = Cache::remember($cacheKey, Carbon::now()->addHours(2), function () use ($code) {
            $dict = static::query()
                ->where('code', $code)
                ->where('status', 1)
                ->first();

            if (! $dict) {
                return [];
            }

            return $dict->dictItems()
                ->where('status', 1)
                ->get()
                ->toArray();
        });

        return DictItem::hydrate($itemsArray);
    }
}
