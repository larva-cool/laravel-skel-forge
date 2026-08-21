<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Uploader;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

/**
 * 图片上传请求
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ImageUploadRequest extends UploadRequest
{
    public bool $imageOptimize = true;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fileRule = settings('upload.allow_image_extension');
        $types = explode(',', $fileRule);

        return [
            'file' => [
                'required',
                'image',
                File::types($types),
            ],
        ];
    }

    /**
     * 处理图片上传
     *
     * @return array 图片信息（含 file_path、file_name、url）
     *
     * @throws ValidationException
     */
    public function handleUpload(): array
    {
        // 开启图片优化
        $optimize = settings('upload.optimize_image', false);
        if ($optimize) {
            $this->imageOptimize = true;
        }

        return parent::handleUpload();
    }
}
