<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\App;

use App\Models\Model;
use Database\Factories\App\AppReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 应用发布模型
 *
 * @property int $id 应用ID
 * @property string $name 应用名称
 * @property string $slug 应用唯一标识
 * @property string|null $description 应用描述
 * @property string|null $icon 应用图标URL
 * @property int $sort 排序权重
 * @property bool $status 状态
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AppVersion> $versions 版本列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('app_releases')]
#[Fillable(['name', 'slug', 'description', 'icon', 'sort', 'status'])]
class AppRelease extends Model
{
    /** @use HasFactory<AppReleaseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'sort' => 1000,
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
            'slug' => 'string',
            'description' => 'string',
            'icon' => 'string',
            'sort' => 'integer',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 版本列表
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AppVersion::class, 'app_release_id');
    }

    /**
     * 各平台最新发布版本
     */
    public function latestVersions(): HasMany
    {
        return $this->versions()->published()->orderByDesc('build');
    }

    /**
     * 作用域：仅启用
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * 作用域：列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['keyword']), function (Builder $q) use ($filters) {
                $keyword = '%'.$filters['keyword'].'%';
                $q->where(function (Builder $sub) use ($keyword) {
                    $sub->where('name', 'like', $keyword)->orWhere('slug', 'like', $keyword);
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', fn (Builder $q) => $q->where('status', (bool) $filters['status']))
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }
}
