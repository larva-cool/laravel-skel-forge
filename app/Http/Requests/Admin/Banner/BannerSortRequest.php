<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Banner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Banner 拖拽排序请求
 *
 * @property-read array $ids 按排序顺序的 Banner ID 数组（同广告位内）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerSortRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:banners,id'],
        ];
    }
}
