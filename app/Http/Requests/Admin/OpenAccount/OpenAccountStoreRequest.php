<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\OpenAccount;

use App\Enums\UserStatus;
use App\Models\Open\OpenAccount;
use App\Rules\NameRule;
use App\Rules\PhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 开放平台账户创建请求（管理端）
 *
 * @property-read string|null $username 用户名
 * @property-read string|null $email 邮箱
 * @property-read string|null $phone 手机号
 * @property-read string $name 合作方名称
 * @property-read string|null $password 初始密码
 * @property-read int $status 状态
 * @property-read string|null $contact_name 联系人
 * @property-read string|null $contact_phone 联系电话
 * @property-read string|null $contact_email 联系邮箱
 * @property-read int $rate_limit 每分钟请求上限
 * @property-read string|null $remark 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class OpenAccountStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['nullable', 'string', 'min:3', 'max:50', Rule::unique(OpenAccount::class, 'username')],
            'email' => ['nullable', 'email', 'max:100', Rule::unique(OpenAccount::class, 'email')],
            'phone' => ['nullable', 'string', new PhoneRule, Rule::unique(OpenAccount::class, 'phone')],
            'name' => ['required', 'string', 'min:2', 'max:50', new NameRule],
            'password' => ['nullable', 'string', Password::defaults()],
            'status' => ['required', 'integer', Rule::enum(UserStatus::class)],
            'contact_name' => ['nullable', 'string', 'max:50'],
            'contact_phone' => ['nullable', 'string', new PhoneRule],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'rate_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }
}
