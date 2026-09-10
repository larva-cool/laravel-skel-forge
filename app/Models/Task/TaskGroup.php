<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Task;

use App\Enums\Task\TaskGroupType;
use App\Enums\Task\TaskResetType;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 任务分组
 *
 * @property int $id 分组ID
 * @property string $name 分组名称
 * @property string $code 分组编码
 * @property TaskGroupType $type 分组类型
 * @property TaskResetType $reset_type 重置类型
 * @property int $sort_order 排序值
 * @property string|null $icon 图标URL
 * @property string|null $description 分组描述
 * @property bool $status 是否启用
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('task_groups')]
#[Fillable(['name', 'code', 'type', 'reset_type', 'sort_order', 'icon', 'description', 'status'])]
class TaskGroup extends Model
{
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'group_id')->orderBy('sort_order');
    }

    public function activeTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'group_id')
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'code' => 'string',
            'type' => TaskGroupType::class,
            'reset_type' => TaskResetType::class,
            'sort_order' => 'integer',
            'icon' => 'string',
            'description' => 'string',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
