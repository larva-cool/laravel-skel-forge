<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Wallet;

use App\Enums\PayChannel;
use App\Enums\RechargeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 充值订单列表筛选请求
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeOrderIndexRequest extends FormRequest
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
            'pay_status' => ['nullable', 'string', Rule::in(RechargeStatus::values())],
            'pay_channel' => ['nullable', 'string', Rule::in(PayChannel::values())],
            'order_no' => ['nullable', 'string', 'max:64'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
