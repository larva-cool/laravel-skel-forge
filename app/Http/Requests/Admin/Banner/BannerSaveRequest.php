<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Banner;

use App\Enums\LinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Banner 保存请求（创建/编辑）
 *
 * @property-read string $position 广告位标识
 * @property-read string $title 标题
 * @property-read string $image 图片 URL
 * @property-read LinkType $link_type 链接类型
 * @property-read string|null $link_value 链接值
 * @property-read int $sort 排序权重
 * @property-read bool $status 启用状态
 * @property-read string|null $start_at 生效开始时间
 * @property-read string|null $end_at 生效结束时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'string', 'max:64'],
            'title' => ['sometimes', 'string', 'max:200'],
            'image' => ['required', 'string', 'max:500'],
            'link_type' => ['required', 'string', Rule::enum(LinkType::class)],
            'link_value' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
            'start_at' => ['sometimes', 'nullable', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after:start_at'],
        ];
    }
}
