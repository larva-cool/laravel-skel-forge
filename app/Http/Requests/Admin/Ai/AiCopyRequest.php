<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AiCopyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'brief' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'brief.required' => '请输入需求简述',
        ];
    }
}
