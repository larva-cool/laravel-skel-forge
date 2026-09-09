<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Wallet\Recharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 充值订单资源（管理端）
 *
 * @mixin Recharge
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeResource extends JsonResource
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
            'pay_channel_label_type' => $this->pay_channel->labelType(),
            'pay_status' => $this->pay_status,
            'pay_status_label_type' => $this->pay_status->labelType(),
            'transaction_id' => $this->transaction_id,
            'confirmed_by' => $this->confirmed_by,
            'client_ip' => $this->client_ip,
            'remark' => $this->remark,
            'is_expired' => $this->isExpired(),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'name' => $this->owner?->name,
            ]),
            // 原始回调报文仅详情接口返回，供对账排查
            'callback_payload' => $this->when(
                $request->routeIs('admin.wallet.recharges.show'),
                fn () => $this->callback_payload
            ),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'expired_at' => $this->expired_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
