<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Point;

use App\Models\Model;
use App\Models\Traits\HasUser;
use App\Support\PointHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * 可用积分记录（内部）
 *
 * @property int $id 流水ID
 * @property int $user_id 用户ID
 * @property int $points 积分
 * @property string $description 描述
 * @property Carbon $expired_at 过期时间
 * @property Carbon $created_at 添加时间
 * @property Carbon $updated_at 更新时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('point_records')]
#[Fillable(['user_id', 'points', 'description', 'expired_at', 'updated_at'])]
#[Hidden(['user_id'])]
class PointRecord extends Model
{
    use HasUser;
    use Prunable;

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
            'expired_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::deleted(function (PointRecord $model) {
            // 删除过期后更新可用积分
            PointHelper::updatePointTotal($model->user_id);
        });
    }

    /**
     * 获取可修剪模型查询构造器。
     */
    public function prunable(): Builder
    {
        return static::query()->where('expired_at', '<=', Carbon::now()->subMonth());
    }
}
