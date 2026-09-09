<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Invite;

use App\Models\Model;
use Database\Factories\Invite\InviteCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 邀请码
 *
 * 邀请码通过多态关联归属到任意主体（如 User、Admin），每个主体仅持有一个邀请码。
 *
 * @property int $id ID
 * @property string $code 邀请码
 * @property int $owner_id 所属主体ID
 * @property string $owner_type 所属主体类型
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Model $owner 所属主体
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('invite_codes')]
#[Fillable(['code', 'owner_id', 'owner_type'])]
class InviteCode extends Model
{
    /** @use HasFactory<InviteCodeFactory> */
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
            'code' => 'string',
            'owner_id' => 'integer',
            'owner_type' => 'string',
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
        static::creating(function (InviteCode $model) {
            if (empty($model->code)) {
                $model->code = static::generateUniqueCode();
            }
        });
    }

    /**
     * 生成一个未被占用的邀请码
     *
     * 应用层查重降低碰撞概率，数据库 uniq_code 唯一索引为最终兜底。
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtolower((string) Str::ulid());
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * 所属主体（多态）
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
