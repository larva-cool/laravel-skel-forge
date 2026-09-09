<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Wallet\WalletTrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 钱包流水资源（管理端）
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
            'before_balance' => $this->before_balance,
            'after_balance' => $this->after_balance,
            'before_frozen' => $this->before_frozen,
            'after_frozen' => $this->after_frozen,
            'type' => $this->type,
            'type_label_type' => $this->type->labelType(),
            'status' => $this->status,
            'status_label_type' => $this->status->labelType(),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'description' => $this->description,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'name' => $this->owner?->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
