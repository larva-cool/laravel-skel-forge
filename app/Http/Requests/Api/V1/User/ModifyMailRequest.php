<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Models\User;
use App\Rules\MailCaptchaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 修改邮箱请求
 *
 * @property-read string $email 邮件地址
 * @property-read string $verify_code 验证码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ModifyMailRequest extends FormRequest
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
            'email' => ['bail', 'required', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'verify_code' => ['required', 'min:4', 'max:7', new MailCaptchaRule('email', $this->ip())],
        ];
    }
}
