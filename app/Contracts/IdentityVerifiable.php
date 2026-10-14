<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Contracts;

use App\Enums\IdentityStatus;
use App\Enums\IdentityType;
use App\Models\User\IdentityVerification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * 实名认证主体契约
 *
 * 用户、其他等可提交实名认证的主体实现该契约。
 * 认证资料统一存放于多态表 identity_verifications（verifiable），主体表仅镜像认证结果。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
interface IdentityVerifiable
{
    /**
     * 实名认证记录（多态一对多）
     *
     * @return MorphMany<int, IdentityVerification>
     */
    public function identityVerifications(): MorphMany;

    /**
     * 最新一条实名认证记录（多态一对一）
     *
     * @return MorphOne<IdentityVerification>
     */
    public function latestIdentity(): MorphOne;

    /**
     * 是否已通过实名认证
     */
    public function isVerified(): bool;

    /**
     * 设置实名认证状态镜像
     */
    public function setIdentityStatus(IdentityStatus $status): bool;

    /**
     * 标记已通过实名认证
     */
    public function markVerified(IdentityType $type, ?IdentityVerification $verification = null): bool;

    /**
     * 标记未通过实名认证
     */
    public function markUnverified(?IdentityStatus $status = null): bool;

    /**
     * 强制固定的认证类型（null 表示不限制，由请求方传值）
     */
    public function forcedIdentityType(): ?IdentityType;
}
