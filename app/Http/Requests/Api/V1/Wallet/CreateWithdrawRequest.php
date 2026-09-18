<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 提交提现申请请求
 *
 * 仅支持选择已绑定的收款账户，不支持临时填写。
 *
 * @property-read string $amount 提现金额
 * @property-read int $withdraw_account_id 收款账户ID
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CreateWithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * 金额区间/每日次数/待审核校验由 WithdrawService 依据系统设置动态校验，此处仅做基础格式兜底。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'withdraw_account_id' => ['required', 'integer', 'exists:withdraw_accounts,id'],
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
            'amount.gt' => '提现金额必须大于 0',
            'withdraw_account_id.required' => '请选择收款账户',
            'withdraw_account_id.exists' => '所选收款账户不存在',
        ];
    }
}
