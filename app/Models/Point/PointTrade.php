<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Point;

use App\Enums\PointType;
use App\Models\Model;
use App\Models\Traits\HasUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 积分明细（面向用户）
 *
 * @property int $id 流水号
 * @property int $user_id 用户ID
 * @property int $points 交易积分数量
 * @property string $description 交易描述
 * @property PointType $type 交易类型
 * @property int $source_id 关联模型ID
 * @property string $source_type 关联模型类型
 * @property Carbon $expired_at 过期时间
 * @property Carbon $created_at 添加时间
 * @property-read string $type_label 类型标签
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('point_trades')]
#[Fillable(['user_id', 'points', 'description', 'type', 'source_id', 'source_type', 'expired_at'])]
#[Hidden(['user_id'])]
class PointTrade extends Model
{
    use HasUser;
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'points' => 'integer',
            'description' => 'string',
            'type' => PointType::class,
            'source_id' => 'integer',
            'source_type' => 'string',
            'expired_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();
        static::created(function (PointTrade $model) {
            if ($model->points > 0) {
                // 创建可用积分明细
                PointRecord::create([
                    'user_id' => $model->user_id,
                    'points' => $model->points,
                    'description' => $model->description,
                    'expired_at' => $model->expired_at,
                ]);
            }
        });
    }

    /**
     * Get the source entity that the Transaction belongs to.
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
