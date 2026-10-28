<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Automation;

use App\Enums\AutomationLogStatus;
use App\Models\Model;
use App\Models\User;
use Database\Factories\Automation\AutomationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 自动化运营-触发日志模型
 *
 * @property int $id 日志ID
 * @property int $rule_id 规则ID
 * @property int $user_id 用户ID
 * @property Carbon $trigger_date 事件日期（如生日/节日当天，非实际推送日期）
 * @property int|null $festival_id 命中节日ID（仅节日类规则）
 * @property array<int, string>|null $channels 实际触达渠道快照
 * @property AutomationLogStatus $status 状态
 * @property string|null $error 失败原因
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property-read AutomationRule $rule 所属规则
 * @property-read User $user 触达用户
 * @property-read AutomationFestival|null $festival 命中节日
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('automation_logs')]
#[Fillable(['rule_id', 'user_id', 'trigger_date', 'festival_id', 'channels', 'status', 'error'])]
class AutomationLog extends Model
{
    /** @use HasFactory<AutomationLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'rule_id' => 'integer',
            'user_id' => 'integer',
            'trigger_date' => 'date',
            'festival_id' => 'integer',
            'channels' => 'array',
            'status' => AutomationLogStatus::class,
            'error' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 所属规则
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }

    /**
     * 触达用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 命中节日
     */
    public function festival(): BelongsTo
    {
        return $this->belongsTo(AutomationFestival::class, 'festival_id');
    }

    /**
     * 作用域：列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['rule_id']), fn (Builder $q) => $q->where('rule_id', (int) $filters['rule_id']))
            ->when(! empty($filters['user_id']), fn (Builder $q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn (Builder $q) => $q->whereDate('trigger_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $q) => $q->whereDate('trigger_date', '<=', $filters['date_to']));
    }
}
