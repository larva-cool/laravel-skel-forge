<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 提现打款失败请求
 *
 * @property-read string $reason 失败原因
 * @property-read string|null $remark 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawFailRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
