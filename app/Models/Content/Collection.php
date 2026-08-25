<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Models\Content;

use App\Models\Model;
use App\Models\Traits\HasUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 收藏
 *
 * @property int $id ID
 * @property int $user_id 用户ID
 * @property int $source_id 资源ID
 * @property string $source_type 资源类型
 * @property Model $source 资源
 * @property array $extra 扩展信息
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property User $user 用户
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[Table('collections')]
#[Fillable(['user_id', 'source_id', 'source_type', 'extra'])]
class Collection extends Model
{
    use HasUser;
    
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
            'source_id' => 'integer',
            'source_type' => 'string',
            'extra' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();
        static::created(function (Collection $model): void {
            $model->source()->increment('collection_count');
        });
        static::deleted(function (Collection $model): void {
            $model->source()->decrement('collection_count');
        });
    }

    /**
     * Get the user relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 多态关联
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
