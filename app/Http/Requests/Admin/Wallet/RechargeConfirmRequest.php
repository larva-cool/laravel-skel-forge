<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 充值订单确认到账请求
 *
 * @property-read string|null $remark 确认备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeConfirmRequest extends FormRequest
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
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
