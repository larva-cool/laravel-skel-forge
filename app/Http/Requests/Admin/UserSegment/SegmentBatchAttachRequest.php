<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\UserSegment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 笛卡尔批量打标/解标请求
 *
 * @property-read array<int, int> $segment_ids 分群 ID 列表
 * @property-read array<int, int> $user_ids 用户 ID 列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentBatchAttachRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'segment_ids' => ['required', 'array', 'min:1'],
            'segment_ids.*' => ['integer', 'min:1'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'min:1'],
        ];
    }
}
