<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 实名认证资源（管理端）
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
        $rejectReasonMap = static::getRejectReasonMap();

        return [
            'id' => $this->id,
            'verifiable_type' => $this->verifiable_type,
            'verifiable_id' => $this->verifiable_id,
            'verifiable' => $this->whenLoaded('verifiable', fn () => $this->verifiable ? [
                'id' => $this->verifiable->id,
                'name' => $this->verifiable->name ?? null,
            ] : null),
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status?->labelType() ?? 'default',
            'real_name' => $this->real_name,
            'id_card_no' => $this->id_card_no,
            'id_card_front' => $this->id_card_front,
            'id_card_back' => $this->id_card_back,
            'handheld' => $this->handheld,
            'company_name' => $this->company_name,
            'credit_code' => $this->credit_code,
            'legal_person_name' => $this->legal_person_name,
            'legal_person_id_card_no' => $this->legal_person_id_card_no,
            'business_license' => $this->business_license,
            'opening_permit' => $this->opening_permit,
            'bank_account' => $this->bank_account,
            'bank_name' => $this->bank_name,
            'admin_id' => $this->admin_id,
            'admin_name' => $this->whenLoaded('admin', fn () => $this->admin?->name),
            'reject_reason' => $this->reject_reason,
            'reject_reason_label' => $this->reject_reason ? ($rejectReasonMap[$this->reject_reason]['label'] ?? $this->reject_reason) : null,
            'reject_reason_label_type' => $this->reject_reason ? ($rejectReasonMap[$this->reject_reason]['label_type'] ?? 'default') : 'default',
            'reject_remark' => $this->reject_remark,
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 获取驳回原因字典映射（带缓存）
     *
     * @return array<string, array{label: string, label_type: string}>
     */
    public static function getRejectReasonMap(): array
    {
        $cacheKey = \App\Enums\CacheKey::key(\App\Enums\CacheKey::DICT_TYPE, 'identity_reject_reason').':map';

        /** @var array<string, array{label: string, label_type: string}>|null $cached */
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $dict = \App\Models\System\Dict::query()
            ->where('code', 'identity_reject_reason')
            ->where('status', 1)
            ->with(['dictItems' => fn ($q) => $q->where('status', 1)->orderBy('sort')->orderBy('id')])
            ->first();

        $map = [];
        foreach ($dict?->dictItems ?? [] as $item) {
            $map[$item->value] = [
                'label' => $item->label,
                'label_type' => $item->label_type ?: 'default',
            ];
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, $map, \Illuminate\Support\Carbon::now()->addHours(2));

        return $map;
    }

    /**
     * 清除驳回原因字典缓存
     */
    public static function flushRejectReasonMapCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget(\App\Enums\CacheKey::key(\App\Enums\CacheKey::DICT_TYPE, 'identity_reject_reason').':map');
    }
}
