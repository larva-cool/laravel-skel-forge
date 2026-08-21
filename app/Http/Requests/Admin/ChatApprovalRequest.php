<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 工具审批续跑请求
 *
 * @property-read string $conversation_id 会话 ID
 * @property-read string $approval_id 待审批的工具调用 ID（PendingApproval->id）
 * @property-read bool $approved 是否批准
 * @property-read string|null $reason 拒绝原因（可选）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ChatApprovalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'string', 'uuid'],
            'approval_id' => ['required', 'string'],
            'approved' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'conversation_id' => '会话ID',
            'approval_id' => '审批ID',
            'approved' => '审批结果',
            'reason' => '拒绝原因',
        ];
    }
}
