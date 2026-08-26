<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Comment;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 评论审核请求
 *
 * @property-read string $status 审核状态
 * @property-read string|null $remark 审核备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CommentReviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ReviewStatus::class)],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }
}
