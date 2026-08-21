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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 地区表
 *
 * @property int $id ID
 * @property int|null $parent_id 父地区ID，null 表示顶级
 * @property string $name 名称
 * @property int|null $city_code 区号
 * @property float|null $lat 纬度
 * @property float|null $lng 经度
 * @property int|null $area_code 区域编码
 * @property int $sort 排序权重，越小越靠前
 * @property Carbon $created_at 添加时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系模型：
 * @property Area|null $parent 父地区
 * @property Collection<int,Area> $children 子地区
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('areas')]
#[Fillable(['parent_id', 'name', 'area_code', 'lat', 'lng', 'city_code', 'sort'])]
class Area extends Model
{
    use HasFactory;

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
            'parent_id' => 'integer',
            'name' => 'string',
            'area_code' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'city_code' => 'string',
            'sort' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();
        $clearCache = function (Area $model) {
            Cache::forget(CacheKey::key(CacheKey::AREA_TREE, $model->parent_id ?? 'root'));
            // 同时清除变更前父级缓存
            $originalParentId = $model->getOriginal('parent_id');
            if ($originalParentId !== $model->parent_id) {
                Cache::forget(CacheKey::key(CacheKey::AREA_TREE, $originalParentId ?? 'root'));
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    /**
     * Get the parent relation.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class);
    }

    /**
     * Get the children relation.
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id', 'id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * Get the recursive children relation.
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * 获取子地区ID
     *
     * @return array<int, int>
     */
    public function getChildrenIds(): array
    {
        return $this->children()
            ->pluck('id')
            ->all();
    }

    /**
     * 获取逗号分隔的子ID
     *
     * @param  int|null  $parentId  父地区ID，null 表示顶级
     */
    public static function getChildIds(?int $parentId = null): string
    {
        if ($parentId === null) {
            return static::query()->whereNull('parent_id')
                ->pluck('id')
                ->implode(',');
        }

        return static::query()->where('parent_id', $parentId)
            ->pluck('id')
            ->implode(',');
    }

    /**
     * 限定顶级地区。
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * 判断是否为顶级地区。
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * 获取地区树形结构（递归加载子地区）。
     *
     * @param  int|null  $parentId  父地区ID，null 表示从顶级开始
     */
    public static function tree(?int $parentId = null): Collection
    {
        $query = static::with('childrenRecursive')
            ->orderBy('sort')
            ->orderBy('id');

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        return $query->get();
    }
}
