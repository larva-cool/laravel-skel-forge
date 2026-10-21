<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\ChatModel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property-read string $provider
 * @property-read string $model_id
 * @property-read string $name
 * @property-read bool $is_default
 * @property-read bool $is_enabled
 * @property-read int $sort
 */
class ChatModelSaveRequest extends FormRequest
{
    /**
     * 准备验证数据：创建时填充默认值（更新时保持原值由前端全量提交）
     */
    protected function prepareForValidation(): void
    {
        if (! $this->route('chat_model')) {
            $this->merge([
                'provider' => $this->input('provider', 'volc'),
                'is_default' => $this->boolean('is_default'),
                'is_enabled' => $this->has('is_enabled') ? $this->boolean('is_enabled') : true,
                'sort' => $this->integer('sort'),
            ]);
        }
    }

    public function rules(): array
    {
        // 服务商 + 模型 ID 联合唯一
        $modelIdUnique = Rule::unique('chat_models', 'model_id')
            ->where(fn ($q) => $q->where('provider', $this->input('provider', 'volc')));

        // 更新时排除自身
        if ($this->route('chat_model')) {
            $modelIdUnique = $modelIdUnique->ignore($this->route('chat_model'));
        }

        return [
            'provider' => ['required', 'string', 'max:50'],
            'model_id' => ['required', 'string', 'max:100', $modelIdUnique],
            'name' => ['required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
