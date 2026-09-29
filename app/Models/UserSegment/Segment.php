<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\UserSegment;

use App\Enums\SegmentType;
use App\Models\Model;
use App\Models\User;
use Database\Factories\UserSegment\SegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 用户分群模型
 *
 * @property int $id 分群 ID
 * @property string|null $code 分群编码（规则分群必填且唯一）
 * @property string $name 分群名称
 * @property string $label_type 标签样式
 * @property SegmentType $type 分群类型：1=手动 2=规则
 * @property string|null $group 分组
 * @property string|null $description 描述
 * @property int $sort 排序权重
 * @property bool $status 启用状态
 * @property array<string,mixed>|null $rule 规则定义（仅 type=RULE）
 * @property Carbon|null $last_synced_at 最近一次规则刷新时间
 * @property int|null $last_synced_user_count 最近一次规则匹配用户数
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系
 * @property-read BelongsToMany<User> $users
 * @property-read HasMany<Membership> $memberships
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('user_segments')]
#[Fillable(['code', 'name', 'label_type', 'type', 'group', 'description', 'sort', 'status', 'rule'])]
class Segment extends Model
{
    /** @use HasFactory<SegmentFactory> */
    use HasFactory;

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
            'code' => 'string',
            'name' => 'string',
            'label_type' => 'string',
            'type' => SegmentType::class,
            'group' => 'string',
            'description' => 'string',
            'sort' => 'integer',
            'status' => 'boolean',
            'rule' => 'array',
            'last_synced_at' => 'datetime',
            'last_synced_user_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 成员（多对多经 user_segment_memberships）
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_segment_memberships', 'segment_id', 'user_id')
            ->withPivot(['source', 'created_at']);
    }

    /**
     * 成员记录（一对多到 Membership）
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'segment_id');
    }

    /**
     * 启用状态
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * 仅手动分群
     */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('type', SegmentType::MANUAL);
    }

    /**
     * 仅规则分群
     */
    public function scopeRule(Builder $query): Builder
    {
        return $query->where('type', SegmentType::RULE);
    }
}
