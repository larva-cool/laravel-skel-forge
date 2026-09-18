<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet\WithdrawAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 提现收款账户资源（前台端）
 *
 * 账号信息脱敏展示。
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
            'real_name' => $this->real_name,
            'account_no' => $this->maskedAccountNo(),
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'is_default' => $this->is_default,
            'is_verified' => $this->is_verified,
            'status' => $this->status,
            'last_used_at' => $this->last_used_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * 脱敏账号展示
     */
    protected function maskedAccountNo(): string
    {
        return $this->resource->maskedAccountNo();
    }
}
