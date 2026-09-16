<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\User\IdentityVerification;

/**
 * 实名认证策略
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityVerificationPolicy
{
    /**
     * 查看任意记录
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * 查看单条记录：本人或管理员
     */
    public function view(User $user, IdentityVerification $verification): bool
    {
        return $user->id === $verification->verifiable_id && $verification->verifiable_type === $user::class;
    }

    /**
     * 提交：未通过认证且没有待审记录
     */
    public function create(User $user): bool
    {
        if ($user->identity_verified) {
            return false;
        }

        $pendingExists = IdentityVerification::query()
            ->where('verifiable_type', $user::class)
            ->where('verifiable_id', $user->id)
            ->whereIn('status', ['pending', 'review'])
            ->exists();

        return ! $pendingExists;
    }

    /**
     * 重新提交：本人 + 当前为 rejected
     */
    public function update(User $user, IdentityVerification $verification): bool
    {
        return $user->id === $verification->verifiable_id
            && $verification->verifiable_type === $user::class
            && $verification->status->isRejected();
    }

    /**
     * 审核（通过/驳回）：仅管理员
     */
    public function review(Admin $admin, IdentityVerification $verification): bool
    {
        return $verification->status->isPending() || $verification->status->isReview();
    }
}
