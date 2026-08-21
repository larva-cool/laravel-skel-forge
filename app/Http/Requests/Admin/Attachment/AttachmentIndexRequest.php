<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Attachment;

use App\Enums\AttachmentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 附件列表请求
 *
 * @property-read string|null $type 附件类型
 * @property-read string|null $disk 存储磁盘
 * @property-read string|null $keyword 关键词
 * @property-read string|null $extension 扩展名
 * @property-read int|null $uploader_id 上传者ID
 * @property-read string|null $start_date 创建时间起始
 * @property-read string|null $end_date 创建时间截止
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', Rule::in(AttachmentType::values())],
            'disk' => ['nullable', 'string', Rule::in(array_keys(config('filesystems.disks', [])))],
            'keyword' => ['nullable', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:32'],
            'uploader_id' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * 获取列表筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only(['type', 'disk', 'keyword', 'extension', 'uploader_id', 'start_date', 'end_date']);
    }
}
