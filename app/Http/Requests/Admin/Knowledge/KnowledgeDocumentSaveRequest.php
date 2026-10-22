<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class KnowledgeDocumentSaveRequest extends FormRequest
{
    /**
     * 准备验证数据：创建时注入当前管理员 ID
     */
    protected function prepareForValidation(): void
    {
        if (! $this->route('knowledge_document')) {
            $this->merge([
                'created_by' => $this->user('admin')?->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'min:10', 'max:200000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'created_by' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => '请输入文档标题',
            'content.required' => '请输入文档内容',
            'content.min' => '文档内容不能少于 10 个字符',
        ];
    }
}
