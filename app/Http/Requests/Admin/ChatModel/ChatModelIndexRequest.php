<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\ChatModel;

use Illuminate\Foundation\Http\FormRequest;

class ChatModelIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'provider' => ['sometimes', 'string', 'max:50'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
