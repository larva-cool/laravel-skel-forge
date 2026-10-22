<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeDocumentIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(KnowledgeDocumentStatus::class)],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
