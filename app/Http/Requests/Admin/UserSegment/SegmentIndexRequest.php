<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\UserSegment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 分群列表筛选请求
 *
 * @property-read string|null $keyword 关键词（名称 / code）
 * @property-read int|null $type 类型：1=手动 2=规则
 * @property-read string|null $group 分组
 * @property-read int|null $status 状态：0=禁用 1=启用
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'integer', 'in:1,2'],
            'group' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }
}
