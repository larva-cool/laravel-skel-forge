<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Banner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Banner 列表筛选请求
 *
 * @property-read string|null $position 广告位标识
 * @property-read string|null $keyword 关键词搜索
 * @property-read int|null $status 状态筛选
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position' => ['sometimes', 'string', 'max:64'],
            'keyword' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
