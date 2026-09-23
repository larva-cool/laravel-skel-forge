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
 * 添加提现收款账户请求
 *
 * @property-read string $account_type 账户类型
 * @property-read string $real_name 真实姓名
 * @property-read string $account_no 收款账号
 * @property-read string|null $bank_name 开户银行
 * @property-read string|null $bank_branch 支行名称
 * @property-read bool|null $is_default 是否设为默认
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class StoreWithdrawAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * 新增账户默认已经过实名认证（实名状态由上层实名流程保证），
     * 这里把它注入到校验后数据中，使控制器可以直接使用 validated() 写入模型。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_verified' => true]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_type' => ['required', 'string', Rule::in(WithdrawAccountType::values())],
            'real_name' => ['required', 'string', 'max:32'],
            'account_no' => ['required', 'string', 'max:64'],
            'bank_name' => ['required_if:account_type,bank', 'nullable', 'string', 'max:64'],
            'bank_branch' => ['nullable', 'string', 'max:128'],
            'is_default' => ['nullable', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
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
            'account_type.in' => '所选账户类型暂不支持',
            'real_name.required' => '请填写真实姓名',
            'account_no.required' => '请填写收款账号',
            'bank_name.required_if' => '银行卡类型必须填写开户银行',
        ];
    }
}
