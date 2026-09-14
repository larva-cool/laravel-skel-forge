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
 * @property-read array<int, array{word: string, category?: string, level?: string}> $words
 */
class SensitiveWordImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'words' => ['required', 'array', 'max:1000'],
            'words.*.word' => ['required', 'string', 'max:64', 'distinct'],
            'words.*.category' => ['sometimes', 'string', Rule::enum(SensitiveCategory::class)],
            'words.*.level' => ['sometimes', 'string', Rule::enum(SensitiveLevel::class)],
        ];
    }
}
