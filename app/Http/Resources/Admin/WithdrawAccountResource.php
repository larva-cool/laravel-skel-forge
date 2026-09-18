<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Wallet\WithdrawAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 提现收款账户资源（管理端）
 *
 * 管理端可见完整账号信息，用于审核打款。
 *
 * @mixin WithdrawAccount
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawAccountResource extends JsonResource
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
            'account_type' => $this->account_type,
            'account_type_label_type' => $this->account_type->labelType(),
            'real_name' => $this->real_name,
            'account_no' => $this->account_no,
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'is_default' => $this->is_default,
            'is_verified' => $this->is_verified,
            'status' => $this->status,
            'status_label_type' => $this->status->labelType(),
            'last_used_at' => $this->last_used_at?->toDateTimeString(),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'name' => $this->owner?->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
