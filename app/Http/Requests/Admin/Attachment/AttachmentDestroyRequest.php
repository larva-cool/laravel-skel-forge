<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Attachment;

use App\Models\System\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 附件批量删除请求
 *
 * @property-read array<int, int> $ids 附件ID集合
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentDestroyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', Rule::exists(Attachment::class, 'id')->withoutTrashed()],
        ];
    }
}
