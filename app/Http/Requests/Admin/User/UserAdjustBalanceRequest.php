<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 用户余额调整请求
 *
 * @property-read string $type 调整类型：points/coins
 * @property-read int $amount 调整金额（正数增加，负数减少）
 * @property-read string|null $description 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserAdjustBalanceRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:points,coins'],
            'amount' => ['required', 'integer', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
