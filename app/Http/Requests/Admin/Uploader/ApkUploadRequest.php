<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Uploader;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File;

/**
 * APK 文件上传请求
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ApkUploadRequest extends UploadRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types('apk'),
            ],
        ];
    }

    /**
     * 获取文件存储目录
     */
    public function getDirectory(): string
    {
        return 'uploads/apk';
    }
}
