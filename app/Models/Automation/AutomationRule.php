<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Automation;

use App\Enums\AutomationRewardType;
use App\Enums\AutomationTriggerType;
use App\Models\Admin\Admin;
use App\Models\Model;
use App\Models\UserSegment\Segment;
use Database\Factories\Automation\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 自动化运营-触达规则模型
 *
 * @property int $id 规则ID
 * @property string $name 规则名称
 * @property AutomationTriggerType $trigger_type 触发类型
 * @property array<string, mixed> $trigger_config 触发配置（advance_days/send_time/festival_ids）
 * @property int|null $segment_id 目标分群ID，为空表示全量用户
 * @property string $title 通知标题模板
 * @property string $content 通知内容模板
 * @property array<int, string> $channels 触达渠道
 * @property AutomationRewardType $reward_type 奖励类型
 * @property int $reward_amount 奖励数量
 * @property bool $status 启用状态
 * @property Carbon|null $last_triggered_at 最近一次触发时间
 * @property int|null $last_triggered_count 最近一次触达用户数
 * @property int|null $admin_id 创建人管理员ID
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read Segment|null $segment 目标分群
 * @property-read Admin|null $admin 创建人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('automation_rules')]
#[Fillable(['name', 'trigger_type', 'trigger_config', 'segment_id', 'title', 'content', 'channels', 'reward_type', 'reward_amount', 'status', 'last_triggered_at', 'last_triggered_count', 'admin_id'])]
class AutomationRule extends Model
{
    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'reward_type' => AutomationRewardType::NONE,
        'reward_amount' => 0,
        'status' => true,
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
            'trigger_type' => AutomationTriggerType::class,
            'trigger_config' => 'array',
            'segment_id' => 'integer',
            'title' => 'string',
            'content' => 'string',
            'channels' => 'array',
            'reward_type' => AutomationRewardType::class,
            'reward_amount' => 'integer',
            'status' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_triggered_count' => 'integer',
            'admin_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 目标分群
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    /**
     * 创建人
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 触发日志
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class, 'rule_id');
    }

    /**
     * 作用域：启用的规则
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
            ->when(! empty($filters['trigger_type']), fn (Builder $q) => $q->where('trigger_type', $filters['trigger_type']))
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', fn (Builder $q) => $q->where('status', (bool) $filters['status']));
    }

    /**
     * 获取触发配置：提前天数（0=当天）
     */
    public function advanceDays(): int
    {
        return max(0, (int) ($this->trigger_config['advance_days'] ?? 0));
    }

    /**
     * 获取触发配置：推送时间（H:i）
     */
    public function sendTime(): string
    {
        return (string) ($this->trigger_config['send_time'] ?? '09:00');
    }

    /**
     * 获取触发配置：限定的节日 ID 列表（仅节日类规则，空数组表示全部启用节日）
     *
     * @return array<int, int>
     */
    public function festivalIds(): array
    {
        return array_values(array_map('intval', (array) ($this->trigger_config['festival_ids'] ?? [])));
    }
}
