<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Uploader;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File;

/**
 * 文件上传请求
 */
class FileUploadRequest extends UploadRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fileRule = settings('upload.allow_extension');
        $types = explode(',', $fileRule);

        return [
            'file' => [
                'required',
                File::types($types),
            ],
        ];
    }
}
