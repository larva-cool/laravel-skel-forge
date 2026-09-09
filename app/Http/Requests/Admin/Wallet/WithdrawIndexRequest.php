<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Wallet;

use App\Enums\WithdrawAccountType;
use App\Enums\WithdrawStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 提现申请列表筛选请求
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawIndexRequest extends FormRequest
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
            'owner_type' => ['nullable', 'string', 'max:64'],
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(WithdrawStatus::values())],
            'account_type' => ['nullable', 'string', Rule::in(WithdrawAccountType::values())],
            'order_no' => ['nullable', 'string', 'max:64'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
