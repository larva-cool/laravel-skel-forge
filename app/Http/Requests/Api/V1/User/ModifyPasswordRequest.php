<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 修改密码请求
 *
 * @property string $old_password 旧密码
 * @property string $password 新密码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ModifyPasswordRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string', 'min:8', 'current_password:sanctum'],
            'password' => ['required', 'string', Password::min(8)->uncompromised()],
        ];
    }
}
