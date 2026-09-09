<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 提现申请资源（前台端）
 *
 * 不暴露持有者、下单IP、审核管理员等内部字段。
 *
 * @mixin Withdraw
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawResource extends JsonResource
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
            'fee' => $this->fee,
            'actual_amount' => $this->actual_amount,
            'account_type' => $this->account_type,
            'account_info' => $this->maskedAccountInfo(),
            'status' => $this->status,
            'reject_reason' => $this->reject_reason,
            'fail_reason' => $this->fail_reason,
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'transfer_at' => $this->transfer_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * 收款账户信息脱敏
     *
     * @return array<string, mixed>
     */
    protected function maskedAccountInfo(): array
    {
        $info = $this->account_info ?? [];

        if (isset($info['account']) && is_string($info['account']) && strlen($info['account']) > 4) {
            $info['account'] = substr($info['account'], 0, 2).'****'.substr($info['account'], -4);
        }

        return $info;
    }
}
