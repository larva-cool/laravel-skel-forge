<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\UserSegment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 分群异步任务列表筛选请求
 *
 * @property-read string|null $status 任务状态
 * @property-read string|null $type 任务类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentTaskIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:pending,running,completed,failed'],
            'type' => ['sometimes', 'string', 'in:attach,detach'],
        ];
    }
}
