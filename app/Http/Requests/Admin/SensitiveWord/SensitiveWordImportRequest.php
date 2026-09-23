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
 * 批量导入敏感词请求
 *
 * @property-read array<int, array{word: string, category?: string, level?: string, replacement?: string, status?: bool}> $words
 */
class SensitiveWordImportRequest extends FormRequest
{
    /**
     * 为每条导入记录注入默认字段（category=OTHER, level=REVIEW, replacement=*, status=true），
     * 控制器可以直接使用 validated() 写入模型，无需在控制器内 array_merge 默认值。
     */
    protected function prepareForValidation(): void
    {
        if (! is_array($words = $this->input('words'))) {
            return;
        }

        $merged = [];
        foreach ($words as $item) {
            if (! is_array($item)) {
                continue;
            }
            $merged[] = array_merge([
                'category' => SensitiveCategory::OTHER->value,
                'level' => SensitiveLevel::REVIEW->value,
                'replacement' => '*',
                'status' => true,
            ], $item);
        }

        $this->merge(['words' => $merged]);
    }

    public function rules(): array
    {
        return [
            'words' => ['required', 'array', 'max:1000'],
            'words.*.word' => ['required', 'string', 'max:64', 'distinct'],
            'words.*.category' => ['sometimes', 'string', Rule::enum(SensitiveCategory::class)],
            'words.*.level' => ['sometimes', 'string', Rule::enum(SensitiveLevel::class)],
            'words.*.replacement' => ['sometimes', 'string', 'max:8'],
            'words.*.status' => ['sometimes', 'boolean'],
        ];
    }
}
