<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Attachment;

use App\Models\System\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 直传完成后登记附件请求
 *
 * @property-read string $path 存储路径
 * @property-read string|null $disk 存储磁盘
 * @property-read string|null $original_name 原始文件名
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255', 'not_regex:/\.\./'],
            'disk' => ['nullable', 'string', Rule::in(array_keys(config('filesystems.disks', [])))],
            'original_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * 附加校验：目标路径必须合法且物理文件真实存在。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! str_starts_with($this->targetPath(), Attachment::PATH_PREFIX)) {
                $validator->errors()->add('path', __('admin.attachment_invalid_target_path'));

                return;
            }

            if (! Storage::disk($this->targetDisk())->exists($this->targetPath())) {
                $validator->errors()->add('path', __('admin.attachment_file_missing'));
            }
        });
    }

    /**
     * 获取目标存储磁盘
     */
    public function targetDisk(): string
    {
        return $this->string('disk')->value() ?: settings('upload.storage', 'public');
    }

    /**
     * 获取目标存储路径
     */
    public function targetPath(): string
    {
        return ltrim($this->string('path')->value(), '/');
    }

    /**
     * 构建入库元数据
     *
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        $disk = Storage::disk($this->targetDisk());
        $path = $this->targetPath();

        return [
            'disk' => $this->targetDisk(),
            'path' => $path,
            'name' => basename($path),
            'original_name' => $this->string('original_name')->value() ?: basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime_type' => $disk->mimeType($path) ?: '',
            'size' => $disk->size($path),
        ];
    }
}
