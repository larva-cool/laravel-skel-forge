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
 * 公告保存请求（创建/编辑）
 *
 * @property-read string $title 标题
 * @property-read string|null $summary 摘要
 * @property-read string $content 正文
 * @property-read string|null $cover_image 封面图 URL
 * @property-read AnnouncementStatus $status 状态
 * @property-read int $sort 排序权重
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AnnouncementSaveRequest extends FormRequest
{
    /**
     * 注入创建人管理员 ID，使控制器可直接用 validated() 写入模型。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['admin_id' => $this->user('admin')?->id]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'cover_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::enum(AnnouncementStatus::class)],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'admin_id' => ['nullable', 'integer'],
        ];
    }
}
