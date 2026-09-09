<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\PayChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 创建充值订单请求
 *
 * @property-read string $amount 充值金额
 * @property-read string $pay_channel 支付渠道
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CreateRechargeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * 金额区间由 RechargeService 依据系统设置动态校验，此处仅做基础格式与上限兜底。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            // 仅允许已接入的支付渠道，避免产生无法支付的僵尸订单
            'pay_channel' => ['required', 'string', Rule::in(PayChannel::availableValues())],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => '充值金额必须大于 0',
            'pay_channel.in' => '所选支付渠道暂未开放',
        ];
    }
}
