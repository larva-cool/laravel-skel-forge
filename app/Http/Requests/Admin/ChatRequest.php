<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 对话请求
 *
 * @property-read string $prompt 用户输入的问题
 * @property-read string|null $conversation_id 会话 ID（继续对话时传入）
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
        return [
            'prompt' => ['required', 'string', 'min:1', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
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
            'prompt' => '对话内容',
            'conversation_id' => '会话ID',
        ];
    }
}
