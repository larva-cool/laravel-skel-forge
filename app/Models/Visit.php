<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasUser;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * 访问记录
 *
 * 记录用户对某个资源（Post / Goods / Video 等）的访问行为，
 * 用于 PV/UV 分析与"最近浏览"等业务场景。append-only，不做撤销语义。
 *
 * @property int $id 记录ID
 * @property int $user_id 用户ID
 * @property string $source_type 源类型
 * @property int $source_id 源ID
 * @property string|null $ip 访问IP（VARBINARY(16)，与 login_histories 一致）
 * @property Carbon|null $created_at 创建时间
 * @property User $user 用户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('visits')]
#[Fillable(['user_id', 'source_type', 'source_id', 'ip'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory, HasUser;

    /**
     * 仅维护 created_at，无 updated_at。
     */
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
            'source_type' => 'string',
            'source_id' => 'integer',
            'ip' => 'string',
            'created_at' => 'datetime',
        ];
    }
}
