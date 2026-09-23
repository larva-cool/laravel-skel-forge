<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\OpenAccount;

use App\Enums\IdentityStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 开放平台账户列表请求（管理端）
 *
 * @property-read string|null $keyword 关键词（名称/用户名/邮箱/手机号/app_id）
 * @property-read int|null $status 状态
 * @property-read string|null $identity_status 实名认证状态
 * @property-read string|null $start_date 创建起始日期
 * @property-read string|null $end_date 创建截止日期
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class OpenAccountIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'integer', Rule::enum(UserStatus::class)],
            'identity_status' => ['nullable', 'string', Rule::enum(IdentityStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * 获取筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'keyword', 'status', 'identity_status', 'start_date', 'end_date',
        ]);
    }
}
