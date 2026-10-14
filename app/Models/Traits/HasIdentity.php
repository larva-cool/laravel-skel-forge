<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\IdentityStatus;
use App\Enums\IdentityType;
use App\Events\User\IdentityVerified;
use App\Models\User\IdentityVerification;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Event;

/**
 * 实名认证主体行为
 *
 * 提供多态实名认证记录关系与认证状态镜像读写能力。
 * 使用方需具备 identity_type / identity_status 字段及对应枚举 cast。
 *
 * @property IdentityType|null $identity_type 实名认证类型镜像
 * @property IdentityStatus $identity_status 实名认证状态镜像
 * @property bool $identity_verified 是否已通过实名认证（逻辑属性，由 identity_status 派生）
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasIdentity
{
    /**
     * 实名认证记录（多态一对多）
     *
     * @return MorphMany<int, IdentityVerification>
     */
    public function identityVerifications(): MorphMany
    {
        return $this->morphMany(IdentityVerification::class, 'verifiable');
    }

    /**
     * 最新实名认证记录（多态一对一，取 ID 最大一条）
     *
     * @return MorphOne<IdentityVerification>
     */
    public function latestIdentity(): MorphOne
    {
        return $this->morphOne(IdentityVerification::class, 'verifiable')
            ->orderByDesc('id');
    }

    /**
     * 是否已通过实名认证
     */
    public function isVerified(): bool
    {
        return $this->identity_status === IdentityStatus::APPROVED;
    }

    /**
     * identity_verified 访问器（逻辑属性，由 identity_status 派生）
     */
    protected function identityVerified(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => ($attributes['identity_status'] ?? null) === IdentityStatus::APPROVED->value
        );
    }

    /**
     * 设置实名认证状态镜像
     */
    public function setIdentityStatus(IdentityStatus $status): bool
    {
        return $this->forceFill(['identity_status' => $status])->saveQuietly();
    }

    /**
     * 标记已通过实名认证
     */
    public function markVerified(IdentityType $type, ?IdentityVerification $verification = null): bool
    {
        $status = $this->forceFill([
            'identity_type' => $type,
            'identity_status' => IdentityStatus::APPROVED,
        ])->saveQuietly();
        Event::dispatch(new IdentityVerified($this, $verification));

        return $status;
    }

    /**
     * 标记未通过实名认证
     */
    public function markUnverified(?IdentityStatus $status = null): bool
    {
        return $this->forceFill([
            'identity_type' => null,
            'identity_status' => $status ?? IdentityStatus::UNSUBMITTED,
        ])->saveQuietly();
    }

    /**
     * 强制固定的认证类型（默认不限制，由请求方传值）
     */
    public function forcedIdentityType(): ?IdentityType
    {
        return null;
    }
}
