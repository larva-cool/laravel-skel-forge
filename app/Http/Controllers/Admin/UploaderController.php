<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Uploader\FileUploadRequest;
use App\Http\Requests\Admin\Uploader\ImageUploadRequest;
use App\Http\Requests\Admin\Uploader\UploadTokenRequest;
use App\Http\Requests\Admin\Uploader\VideoUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * 上传控制器
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class UploaderController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 通用文件上传
     *
     * @throws ValidationException
     */
    public function file(FileUploadRequest $request): array
    {
        return $request->handleUpload();
    }

    /**
     * 通用图片上传
     *
     * @throws ValidationException
     */
    public function image(ImageUploadRequest $request): array
    {
        return $request->handleUpload();
    }

    /**
     * 通用视频上传
     *
     * @throws ValidationException
     */
    public function video(VideoUploadRequest $request): array
    {
        return $request->handleUpload();
    }

    /**
     * 读取远程上传 Token
     */
    public function uploadToken(UploadTokenRequest $request): JsonResponse
    {
        $path = $request->generateFilePath();
        $result = Storage::temporaryUploadUrl($path, Carbon::now()->addMinutes(10));

        $headers = $result['headers'] ?? [];
        unset($headers['host']);

        return response()->json([
            'url' => $result['url'] ?? '',
            'headers' => $headers,
            'path' => $path,
        ]);
    }
}
