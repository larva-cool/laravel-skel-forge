<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\Uploader\UploadRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * 修改头像请求
 *
 * @property UploadedFile $avatar 上传的头像文件
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ModifyAvatarRequest extends UploadRequest
{
    /**
     * 是否优化图片（头像统一转换为 webp）
     */
    public bool $imageOptimize = true;

    /**
     * 上传文件的表单字段名
     */
    protected string $fileField = 'avatar';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $types = explode(',', (string) settings('upload.allow_image_extension', 'jpg,png,gif,jpeg'));

        return [
            'avatar' => [
                'required',
                'image',
                File::types($types),
            ],
        ];
    }

    /**
     * 生成文件名（用用户 ID 命名，重复上传直接覆盖旧头像）
     */
    protected function generateFileName(UploadedFile $file): string
    {
        return $this->user()->id.'.'.$this->getFileExtension($file);
    }

    /**
     * 获取文件存储目录（按用户 ID 补零至 9 位后分 3 位、4 位两级目录）
     */
    public function getDirectory(): string
    {
        $paddedId = sprintf('%09d', $this->user()->id);

        return 'avatar/'.substr($paddedId, 0, 3).'/'.substr($paddedId, 3, 4);
    }
}
