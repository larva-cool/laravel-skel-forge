<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Wallet;

use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 钱包流水列表筛选请求
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletTradeIndexRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(WalletTradeType::values())],
            'status' => ['nullable', 'string', Rule::in(WalletTradeStatus::values())],
            'keyword' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
