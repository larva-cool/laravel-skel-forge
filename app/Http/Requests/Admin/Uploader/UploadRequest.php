<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Uploader;

use App\Models\System\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * 上传请求基类
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
abstract class UploadRequest extends FormRequest
{
    /**
     * 是否开启图片优化
     */
    public bool $imageOptimize = false;

    /**
     * 缓存的存储磁盘名称
     */
    protected ?string $storageDisk = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract public function rules(): array;

    /**
     * 处理文件上传
     *
     * @return array 文件信息（含 file_path、file_name、url）
     *
     * @throws ValidationException
     */
    public function handleUpload(): array
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');
        $fileName = $this->generateFileName($file);
        $filePath = $this->storeFile($file, $fileName);

        if ($filePath === false) {
            throw ValidationException::withMessages([
                'file' => '上传失败，请重试',
            ]);
        }

        $result = $this->buildUploadResult($file, $fileName, $filePath);
        $attachment = $this->recordAttachment($result);

        return ['id' => $attachment->id, 'url' => app(AttachmentService::class)->url($attachment)] + $result;
    }

    /**
     * 将上传结果写入附件台账
     */
    protected function recordAttachment(array $result): Attachment
    {
        return app(AttachmentService::class)->record([
            'disk' => $result['storage'],
            'path' => $result['file_path'],
            'name' => $result['file_name'],
            'original_name' => $result['origin_name'],
            'extension' => $result['file_ext'],
            'mime_type' => $result['mime_type'],
            'size' => (int) $result['file_size'],
        ], $this->user());
    }

    /**
     * 生成唯一文件名（含扩展名）
     */
    protected function generateFileName(UploadedFile $file): string
    {
        return md5(uniqid(microtime(), true)).'.'.$this->getFileExtension($file);
    }

    /**
     * 存储文件并返回存储路径
     */
    protected function storeFile(UploadedFile $file, string $fileName): string|false
    {
        $disk = $this->getStorageDisk();
        $directory = $this->getDirectory();

        if ($this->imageOptimize) {
            return Image::fromUpload($file)->quality(60)->toWebp()->storePubliclyAs($directory, $fileName, $disk);
        }

        return Storage::disk($disk)->putFileAs($directory, $file, $fileName);
    }

    /**
     * 组装上传结果
     */
    protected function buildUploadResult(UploadedFile $file, string $fileName, string $filePath): array
    {
        return [
            'storage' => $this->getStorageDisk(),
            'origin_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'file_ext' => $this->getFileExtension($file),
            'mime_type' => $this->imageOptimize ? 'image/webp' : $file->getClientMimeType(),
        ];
    }

    /**
     * 获取文件扩展名（图片优化后返回 webp）
     */
    protected function getFileExtension(UploadedFile $file): string
    {
        return $this->imageOptimize ? 'webp' : $file->getClientOriginalExtension();
    }

    /**
     * 获取存储磁盘名称（带缓存）
     */
    protected function getStorageDisk(): string
    {
        if ($this->storageDisk === null) {
            $this->storageDisk = settings('upload.storage', 'public');
        }

        return $this->storageDisk;
    }

    /**
     * 获取文件存储目录
     */
    public function getDirectory(): string
    {
        return 'uploads/'.date('Y/m/d');
    }
}
