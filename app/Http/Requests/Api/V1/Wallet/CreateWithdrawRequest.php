<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\WithdrawAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 提交提现申请请求
 *
 * @property-read string $amount 提现金额
 * @property-read string $account_type 账户类型
 * @property-read array $account_info 账户信息（含 account、real_name）
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
        return true;
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
            'account_type' => ['required', 'string', Rule::in(WithdrawAccountType::values())],
            'account_info' => ['required', 'array'],
            'account_info.account' => ['required', 'string', 'max:64'],
            'account_info.real_name' => ['required', 'string', 'max:32'],
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
            'account_type.in' => '所选账户类型暂不支持',
            'account_info.account.required' => '请填写收款账号',
            'account_info.real_name.required' => '请填写真实姓名',
        ];
    }
}
