<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Invite;

use App\Models\Model;
use Database\Factories\Invite\InviteRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 邀请记录
 *
 * @property int $id ID
 * @property int $inviter_id 邀请人ID
 * @property string $inviter_type 邀请人类型
 * @property int $invitee_id 被邀请人ID
 * @property string $invitee_type 被邀请人类型
 * @property string|null $description 描述/备注
 * @property Carbon $created_at 创建时间
 * @property Model $inviter 邀请人
 * @property Model $invitee 被邀请人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('invite_records')]
#[Fillable(['inviter_id', 'inviter_type', 'invitee_id', 'invitee_type', 'description'])]
class InviteRecord extends Model
{
    /** @use HasFactory<InviteRecordFactory> */
    use HasFactory;
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
            'inviter_id' => 'integer',
            'inviter_type' => 'string',
            'invitee_id' => 'integer',
            'invitee_type' => 'string',
            'description' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 邀请人（多态）
     */
    public function inviter(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 被邀请人（多态）
     */
    public function invitee(): MorphTo
    {
        return $this->morphTo();
    }
}
