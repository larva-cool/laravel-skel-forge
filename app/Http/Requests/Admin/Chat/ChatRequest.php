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
 * AI 对话请求
 *
 * @property-read string $prompt 用户输入的问题
 * @property-read string|null $conversation_id 会话 ID（继续对话时传入）
 * @property-read string|null $agent 智能体标识（assistant / finance）
 * @property-read string|null $model 指定调用的模型 ID
 * @property-read array<int, int>|null $attachment_ids 图片附件 ID 列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ChatRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'min:1', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
            'agent' => ['nullable', 'string', Rule::in(['assistant', 'finance'])],
            'model' => array_filter([
                'nullable',
                'string',
                'max:100',
                $availableModels !== [] ? Rule::in($availableModels) : null,
            ]),
            'attachment_ids' => ['nullable', 'array', 'max:4'],
            'attachment_ids.*' => ['integer', 'exists:attachments,id'],
        ];
    }
}
