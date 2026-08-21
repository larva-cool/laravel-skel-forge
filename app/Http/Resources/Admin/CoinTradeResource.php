<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Coin\CoinTrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 金币流水资源
 *
 * @mixin CoinTrade
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CoinTradeResource extends JsonResource
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
            'coins' => $this->coins,
            'description' => $this->description,
            'type' => $this->type,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
