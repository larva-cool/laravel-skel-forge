<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Identity;

use App\Enums\IdentityType;
use App\Enums\ReviewStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 实名认证列表请求（管理端）
 *
 * @property-read int|null $user_id 用户ID
 * @property-read string|null $keyword 关键词
 * @property-read string|null $type 认证类型
 * @property-read string|null $status 状态
 * @property-read string|null $start_date 起始日期
 * @property-read string|null $end_date 截止日期
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'min:1'],
            'keyword' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', Rule::enum(IdentityType::class)],
            'status' => ['nullable', 'string', Rule::enum(ReviewStatus::class)],
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
            'user_id', 'keyword', 'type', 'status', 'start_date', 'end_date',
        ]);
    }
}
