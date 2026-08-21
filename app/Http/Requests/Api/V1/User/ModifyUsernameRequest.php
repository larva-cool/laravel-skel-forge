<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Models\User;
use App\Rules\UsernameChangeRule;
use App\Rules\UsernameRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 修改用户名请求
 *
 * @property string $username 用户名
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ModifyUsernameRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'username' => [
                'bail', 'required', 'string', 'max:20',
                new UsernameRule,
                new UsernameChangeRule($this->user()),
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
