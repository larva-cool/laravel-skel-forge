<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Admin;

use App\Http\Requests\Admin\Uploader\UploadRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * 修改管理员头像请求
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AdminUpdateAvatarRequest extends UploadRequest
{
    /**
     * 是否优化图片
     */
    public bool $imageOptimize = true;

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
                'image',
            ],
        ];
    }

    /**
     * 生成文件名（用管理员 ID 命名）
     */
    protected function generateFileName(UploadedFile $file): string
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $this->user();

        return $admin->id.'.'.$this->getFileExtension($file);
    }

    /**
     * 获取文件存储目录（按管理员 ID 分 3 位一级目录，共 9 位）
     */
    public function getDirectory(): string
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $this->user();
        $paddedId = str_pad((string) $admin->id, 9, '0', STR_PAD_LEFT);

        return 'admin_avatars/'.substr($paddedId, 0, 3).'/'.substr($paddedId, 3, 3).'/'.substr($paddedId, 6, 3);
    }
}
