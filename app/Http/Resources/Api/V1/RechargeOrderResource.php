<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet\RechargeOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 充值订单资源（前台端）
 *
 * 不暴露持有者、下单IP、渠道回调报文等内部字段。
 *
 * @mixin RechargeOrder
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeOrderResource extends JsonResource
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
            'order_no' => $this->order_no,
            'amount' => $this->amount,
            'bonus_amount' => $this->bonus_amount,
            'total_amount' => $this->totalAmount(),
            'pay_channel' => $this->pay_channel,
            'pay_status' => $this->pay_status,
            'is_expired' => $this->isExpired(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'expired_at' => $this->expired_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
