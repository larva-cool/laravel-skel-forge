<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Events\User\IdentityRejected;
use App\Events\User\IdentityVerified;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\User\IdentityVerification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 实名认证服务
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityVerificationService
{
    /**
     * 默认认证有效期（年）
     */
    public const int DEFAULT_EXPIRES_YEARS = 3;

    /**
     * 提交认证（用户）
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, array $data): IdentityVerification
    {
        return DB::transaction(function () use ($user, $data) {
            // 同一用户仅允许一条待审记录
            $existing = $user->identityVerifications()
                ->whereIn('status', [ReviewStatus::PENDING, ReviewStatus::REVIEW])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                abort(422, __('system.identity_pending_exists'));
            }

            $data['status'] = ReviewStatus::PENDING;
            $data['submitted_at'] = Carbon::now();

            $verification = $user->identityVerifications()->create($data);

            // 同步用户实名认证状态为待审核
            $user->setIdentityStatus(ReviewStatus::PENDING);

            return $verification;
        });
    }

    /**
     * 审核通过（管理员）
     */
    public function approve(IdentityVerification $verification, Admin $admin): IdentityVerification
    {
        return DB::transaction(function () use ($verification, $admin) {
            $verification->forceFill([
                'status' => ReviewStatus::APPROVED,
                'admin_id' => $admin->id,
                'reviewed_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addYears(self::DEFAULT_EXPIRES_YEARS),
                'reject_reason' => null,
                'reject_remark' => null,
            ])->save();

            // 同步用户认证类型与状态
            $verification->verifiable->forceFill([
                'identity_type' => $verification->type,
                'identity_status' => ReviewStatus::APPROVED,
            ])->saveQuietly();

            IdentityVerified::dispatch($verification->verifiable, $verification);

            return $verification->refresh();
        });
    }

    /**
     * 审核驳回（管理员）
     *
     * @param  string  $reason  驳回原因（字典 identity_reject_reason 的 value）
     */
    public function reject(IdentityVerification $verification, Admin $admin, string $reason, ?string $remark = null): IdentityVerification
    {
        return DB::transaction(function () use ($verification, $admin, $reason, $remark) {
            $verification->forceFill([
                'status' => ReviewStatus::REJECTED,
                'admin_id' => $admin->id,
                'reviewed_at' => Carbon::now(),
                'reject_reason' => $reason,
                'reject_remark' => $remark,
            ])->save();

            // 同步用户实名认证状态为已驳回
            $verification->verifiable->setIdentityStatus(ReviewStatus::REJECTED);

            IdentityRejected::dispatch($verification->verifiable, $verification, $reason, $remark);

            return $verification->refresh();
        });
    }
}
