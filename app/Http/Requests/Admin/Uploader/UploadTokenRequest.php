<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Uploader;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 远程上传 Token
 *
 * @property-read string $filename 文件名
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class UploadTokenRequest extends FormRequest
{
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
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string'],
        ];
    }

    /**
     * 生成远程上传的目标路径
     */
    public function generateFilePath(): string
    {
        $extension = pathinfo($this->string('filename')->value(), \PATHINFO_EXTENSION);
        $fileName = md5(uniqid(microtime(), true)).'.'.$extension;

        return 'uploads/'.date('Y/m/d').'/'.$fileName;
    }
}
