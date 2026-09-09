<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Events\Invite\InviteRelationshipCreated;
use App\Models\Invite\InviteCode;
use App\Models\Invite\InviteRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * 邀请服务
 *
 * 负责邀请关系的建立与查询，作为邀请系统的业务编排入口。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteService
{
    /**
     * 通过邀请码建立邀请关系
     *
     * 用邀请码反查邀请码所属主体（多态 owner），创建 InviteRecord 并触发事件。
     * 幂等：同一被邀请人只会建立一次关系，重复调用返回 null。
     *
     * @param  Model  $invitee  被邀请人模型实例
     * @param  string  $inviteCode  邀请码
     * @param  string|null  $description  描述
     * @return InviteRecord|null 成功返回记录，邀请码无效或已存在关系返回 null
     */
    public function createRelationshipByCode(Model $invitee, string $inviteCode, ?string $description = null): ?InviteRecord
    {
        // 通过邀请码找到所属主体（邀请人）
        $inviteCodeModel = InviteCode::where('code', $inviteCode)->first();
        $inviter = $inviteCodeModel?->owner;
        if (! $inviter) {
            return null;
        }

        // 自己不能邀请自己（多态主体需同时比较类型与 ID）
        if ($inviter->getMorphClass() === $invitee->getMorphClass()
            && $inviter->getKey() === $invitee->getKey()
        ) {
            return null;
        }

        // 已存在邀请关系则直接返回 null
        if (InviteRecord::where('invitee_id', $invitee->getKey())
            ->where('invitee_type', $invitee->getMorphClass())
            ->exists()
        ) {
            return null;
        }

        try {
            return DB::transaction(function () use ($inviter, $invitee, $description) {
                $record = InviteRecord::create([
                    'inviter_id' => $inviter->getKey(),
                    'inviter_type' => $inviter->getMorphClass(),
                    'invitee_id' => $invitee->getKey(),
                    'invitee_type' => $invitee->getMorphClass(),
                    'description' => $description,
                ]);

                Event::dispatch(new InviteRelationshipCreated($record));

                return $record;
            });
        } catch (UniqueConstraintViolationException $e) {
            // 并发场景下的唯一索引冲突，返回 null 表示未新建
            return null;
        }
    }

    /**
     * 判断用户是否有邀请人
     */
    public function hasInviter(Model $user): bool
    {
        return InviteRecord::where('invitee_id', $user->getKey())
            ->where('invitee_type', $user->getMorphClass())
            ->exists();
    }

    /**
     * 获取用户的邀请记录
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getInviteeRecords(Model $inviter, int $perPage = 15)
    {
        return InviteRecord::where('inviter_id', $inviter->getKey())
            ->where('inviter_type', $inviter->getMorphClass())
            ->latest('id')
            ->paginate($perPage);
    }
}
