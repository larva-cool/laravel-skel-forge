<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\SensitiveWord;

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property-read string $word
 * @property-read SensitiveCategory $category
 * @property-read SensitiveLevel $level
 * @property-read string $replacement
 * @property-read bool $status
 */
class SensitiveWordSaveRequest extends FormRequest
{
    public function rules(): array
    {
        $wordUnique = Rule::unique('sensitive_words', 'word')
            ->where(fn ($q) => $q->whereNull('deleted_at'));

        // 更新时排除自身
        if ($this->route('sensitive_word')) {
            $wordUnique = $wordUnique->ignore($this->route('sensitive_word'));
        }

        return [
            'word' => ['required', 'string', 'max:64', $wordUnique],
            'category' => ['sometimes', 'string', Rule::enum(SensitiveCategory::class)],
            'level' => ['sometimes', 'string', Rule::enum(SensitiveLevel::class)],
            'replacement' => ['sometimes', 'string', 'max:16'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
