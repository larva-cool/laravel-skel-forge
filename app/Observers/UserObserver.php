<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Observers;

use App\Models\Content\Collection;
use App\Models\Content\Comment;
use App\Models\Content\Follow;
use App\Models\Content\Like;
use App\Models\Content\Report;
use App\Models\Content\Visit;
use App\Models\System\Feedback;
use App\Models\User;
use App\Models\User\Address;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $user->extra()->create();
        $user->profile()->create();
        $user->inviteCode()->create();
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $user->tokens()->delete();
        // 软删时清理分群成员记录（外键 CASCADE 仅对硬删生效，软删需要手动清理）
        $user->memberships()->delete();
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     *
     * 彻底清理用户关联数据。财务流水（钱包/充值/提现/金币/积分）与附件台账
     * 作为审计凭证保留，不在此处清理；任务进度/日志由外键级联删除。
     */
    public function forceDeleted(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->extra->delete();
            $user->profile->delete();
            $user->identityVerifications()->delete();
            $user->loginHistories()->delete();
            $user->socials()->delete();

            // 邀请关系（作为邀请人与被邀请人）
            $user->inviteCode()->delete();
            $user->invitees()->delete();
            $user->inviterRelation()->delete();

            // 提现收款账户（敏感个人信息，软删模型需彻底删除）
            $user->withdrawAccounts()->withTrashed()->forceDelete();

            // 评论（逐条删除以触发 CommentObserver 递归清理回复）
            Comment::forUser($user)->get()->each->delete();

            // 互动行为
            Like::forUser($user)->delete();
            Collection::forUser($user)->delete();
            Follow::forUser($user)->delete();
            Visit::forUser($user)->delete();

            // 举报（作为举报人与被举报对象）
            Report::where('user_id', $user->id)->delete();
            $user->reports()->delete();

            // 其他个人数据
            Address::withTrashed()->forUser($user)->forceDelete();
            Feedback::where('user_id', $user->id)->delete();
            $user->notifications()->delete();
        });
    }
}
