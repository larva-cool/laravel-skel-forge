<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Feedback;

use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 反馈更新请求（管理端处理反馈）
 *
 * @property-read int|null $status 状态
 * @property-read int|null $priority 优先级
 * @property-read string|null $admin_remark 处理备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'integer', Rule::enum(FeedbackStatus::class)],
            'priority' => ['sometimes', 'integer', Rule::enum(FeedbackPriority::class)],
            'admin_remark' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
