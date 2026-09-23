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
 * 开放平台账户更新请求（管理端）
 *
 * @property-read string|null $email 邮箱
 * @property-read string|null $phone 手机号
 * @property-read string $name 合作方名称
 * @property-read string|null $password 密码（留空则不修改）
 * @property-read int $status 状态
 * @property-read string|null $contact_name 联系人
 * @property-read string|null $contact_phone 联系电话
 * @property-read string|null $contact_email 联系邮箱
 * @property-read int $rate_limit 每分钟请求上限
 * @property-read string|null $remark 备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class OpenAccountUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $accountId = (int) $this->route('open_account')?->id ?? (int) $this->route('open_account');

        return [
            'email' => [
                'nullable', 'email', 'max:100',
                Rule::unique(OpenAccount::class, 'email')->ignore($accountId),
            ],
            'phone' => [
                'nullable', 'string', new PhoneRule,
                Rule::unique(OpenAccount::class, 'phone')->ignore($accountId),
            ],
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
