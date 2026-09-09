<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 钱包余额调整请求
 *
 * @property-read string $amount 调整金额（正数充正，负数扣减）
 * @property-read string $description 调账说明（必填，用于审计追溯）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletAdjustRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'not_in:0', 'decimal:0,2', 'min:-99999999.99', 'max:99999999.99'],
            'description' => ['required', 'string', 'max:255'],
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
            'amount.not_in' => '调整金额不能为 0',
            'description.required' => '调账说明为必填项',
        ];
    }
}
