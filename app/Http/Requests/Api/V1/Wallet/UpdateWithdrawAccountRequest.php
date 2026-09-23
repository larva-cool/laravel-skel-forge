<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\WithdrawAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 修改提现收款账户请求
 *
 * @property-read string|null $real_name 真实姓名
 * @property-read string|null $account_no 收款账号
 * @property-read string|null $bank_name 开户银行
 * @property-read string|null $bank_branch 支行名称
 * @property-read string|null $status 状态
 * @property-read bool|null $is_default 是否设为默认
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UpdateWithdrawAccountRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'real_name' => ['sometimes', 'string', 'max:32'],
            'account_no' => ['sometimes', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:64'],
            'bank_branch' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'string', Rule::in(WithdrawAccountStatus::values())],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * 获取用于更新的字段集（自动剔除空值，避免覆盖现有数据）。
     *
     * @return array<string, mixed>
     */
    public function updateData(): array
    {
        return array_filter(
            $this->validated(),
            fn ($value) => $value !== null,
        );
    }
}
