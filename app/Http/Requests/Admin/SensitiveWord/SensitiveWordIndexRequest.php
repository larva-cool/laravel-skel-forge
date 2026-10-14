<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\SensitiveWord;

use Illuminate\Foundation\Http\FormRequest;

class SensitiveWordIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:64'],
            'category' => ['sometimes', 'string', 'max:20'],
            'level' => ['sometimes', 'string', 'max:20'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
