<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet\WalletTrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 钱包流水资源（前台端）
 *
 * 前台端不暴露 owner 信息，所有流水均属于当前登录用户。
 *
 * @mixin WalletTrade
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletTradeResource extends JsonResource
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
            'amount' => $this->amount,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'status' => $this->status,
            'description' => $this->description,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
