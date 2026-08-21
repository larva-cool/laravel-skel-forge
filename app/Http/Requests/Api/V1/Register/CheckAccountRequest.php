<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Register;

use App\Rules\PhoneRule;
use App\Rules\UsernameRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 检测账户请求
 *
 * @property string|null $username 用户名
 * @property string|null $email 邮箱
 * @property string|null $phone 手机号
 */
class CheckAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required_without_all:email,phone', 'string', new UsernameRule],
            'email' => ['required_without_all:username,phone', 'string', 'email'],
            'phone' => ['required_without_all:username,email', 'string', new PhoneRule],
        ];
    }
}
