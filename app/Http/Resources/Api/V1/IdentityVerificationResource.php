<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 实名认证资源（用户端）
 *
 * @mixin IdentityVerification
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'real_name' => $this->type?->isPersonal() ? $this->real_name : null,
            'company_name' => $this->type?->isEnterprise() ? $this->company_name : null,
            'id_card_front' => $this->id_card_front,
            'id_card_back' => $this->id_card_back,
            'handheld' => $this->handheld,
            'business_license' => $this->business_license,
            'opening_permit' => $this->opening_permit,
            'reject_reason' => $this->reject_reason,
            'reject_remark' => $this->reject_remark,
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
