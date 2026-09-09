<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support\Traits;

use App\Models\Invite\InviteCode;
use App\Models\Invite\InviteRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * 邀请关联 Trait
 *
 * 为 User、Admin 等模型提供邀请码、邀请人 / 被邀请人相关的关联与快捷方法。
 *
 * @property Collection<int, InviteRecord> $invitees 我邀请的人
 * @property InviteRecord|null $inviterRelation 我的邀请关系（作为被邀请人）
 * @property InviteCode|null $inviteCode 我的邀请码
 *
 * @method MorphMany invitees() 我邀请的人
 * @method MorphOne inviterRelation() 我的邀请关系
 * @method MorphOne inviteCode() 我的邀请码
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasInvitation
{
    /**
     * 我邀请的人（作为邀请方，反向关联多条邀请记录）
     */
    public function invitees(): MorphMany
    {
        return $this->morphMany(InviteRecord::class, 'inviter');
    }

    /**
     * 我的邀请关系（作为被邀请方，反向关联一条邀请记录）
     */
    public function inviterRelation(): MorphOne
    {
        return $this->morphOne(InviteRecord::class, 'invitee');
    }

    /**
     * 我的邀请码（多态归属，一个主体仅持有一个邀请码）
     */
    public function inviteCode(): MorphOne
    {
        return $this->morphOne(InviteCode::class, 'owner');
    }

    /**
     * 获取我的邀请码
     */
    public function getInviteCode(): string
    {
        return $this->inviteCode?->code ?? '';
    }

    /**
     * 是否有邀请人
     */
    public function hasInviter(): bool
    {
        return $this->inviterRelation !== null;
    }

    /**
     * 获取邀请人数
     */
    public function getInviteeCount(): int
    {
        return $this->invitees()->count();
    }
}
