<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\UserSegment;

use App\Models\Model;
use App\Models\User;
use Database\Factories\UserSegment\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 用户分群成员记录
 *
 * @property int $id
 * @property int $segment_id 分群 ID
 * @property int $user_id 用户 ID
 * @property int $source 打标来源：1=手动 2=规则
 * @property Carbon|null $created_at 创建时间
 *
 * 关系
 * @property-read Segment $segment
 * @property-read User $user
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('user_segment_memberships')]
#[Fillable(['segment_id', 'user_id', 'source'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;
    public const UPDATED_AT = null;

    /** 来源：手动 */
    public const SOURCE_MANUAL = 1;

    /** 来源：规则 */
    public const SOURCE_RULE = 2;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'segment_id' => 'integer',
            'user_id' => 'integer',
            'source' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 所属分群
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    /**
     * 所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 是否为规则同步来源
     */
    public function isRuleSource(): bool
    {
        return (int) $this->source === self::SOURCE_RULE;
    }

    /**
     * 来源标签
     */
    public function sourceLabel(): string
    {
        return match ((int) $this->source) {
            self::SOURCE_RULE => '规则',
            default => '手动',
        };
    }
}
