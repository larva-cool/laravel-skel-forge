<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Chat;

use App\Models\Ai\ChatModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AI 工具审批续跑请求
 *
 * @property-read string $conversation_id 会话 ID
 * @property-read string $approval_id 待审批的工具调用 ID（PendingApproval->id）
 * @property-read bool $approved 是否批准
 * @property-read string|null $reason 拒绝原因（可选）
 * @property-read string|null $agent 智能体标识（assistant / finance）
 * @property-read string|null $model 指定调用的模型 ID
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
        $availableModels = ChatModel::availableIds();

        return [
            'conversation_id' => ['required', 'string', 'uuid'],
            'approval_id' => ['required', 'string'],
            'approved' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
            'agent' => ['nullable', 'string', Rule::in(['assistant', 'finance'])],
            'model' => array_filter([
                'nullable',
                'string',
                'max:100',
                $availableModels !== [] ? Rule::in($availableModels) : null,
            ]),
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
            'reason' => __('validation.attributes.chat_reason'),
        ];
    }
}
