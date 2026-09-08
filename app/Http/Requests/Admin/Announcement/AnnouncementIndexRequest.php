<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Announcement;

use App\Enums\AnnouncementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 公告列表筛选请求
 *
 * @property-read string|null $keyword 关键词搜索
 * @property-read string|null $status 状态筛选
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AnnouncementIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::enum(AnnouncementStatus::class)],
        ];
    }
}
