<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Feedback;

use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Models\System\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 反馈更新请求（管理端处理反馈）
 *
 * @property-read int|null $status 状态
 * @property-read int|null $priority 优先级
 * @property-read string|null $admin_remark 处理备注
 * @property-read int|null $admin_id 处理人ID（自动注入）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackUpdateRequest extends FormRequest
{
    /**
     * 处理人字段自动注入：
     * 当请求中携带 status 且该反馈尚未指派处理人时，将当前管理员 ID 注入到 validated() 中。
     * 控制器可直接使用 validated() 写入模型。
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $feedback = $this->route('feedback');
            $feedback ??= Feedback::query()->find((int) $this->route('id'));

            if ($feedback instanceof Feedback && $feedback->admin_id === null) {
                $this->merge(['admin_id' => $this->user('admin')?->id]);
            }
        }
    }

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
            'admin_id' => ['nullable', 'integer'],
        ];
    }
}
