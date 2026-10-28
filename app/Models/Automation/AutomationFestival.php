<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Automation;

use App\Enums\FestivalDateType;
use App\Models\Model;
use Database\Factories\Automation\AutomationFestivalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 自动化运营-节日定义模型
 *
 * @property int $id 节日ID
 * @property string $name 节日名称
 * @property FestivalDateType $date_type 日期类型：solar=公历 lunar=农历
 * @property int $month 月份
 * @property int $day 日
 * @property bool $status 启用状态
 * @property int $sort 排序权重
 * @property string|null $remark 备注
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('automation_festivals')]
#[Fillable(['name', 'date_type', 'month', 'day', 'status', 'sort', 'remark'])]
class AutomationFestival extends Model
{
    /** @use HasFactory<AutomationFestivalFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'date_type' => FestivalDateType::SOLAR,
        'status' => true,
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
            'date_type' => FestivalDateType::class,
            'month' => 'integer',
            'day' => 'integer',
            'status' => 'boolean',
            'sort' => 'integer',
            'remark' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 作用域：启用的节日
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
            ->when(! empty($filters['keyword']), fn (Builder $q) => $q->where('name', 'like', '%'.$filters['keyword'].'%'))
            ->when(! empty($filters['date_type']), fn (Builder $q) => $q->where('date_type', $filters['date_type']))
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', fn (Builder $q) => $q->where('status', (bool) $filters['status']));
    }
}
