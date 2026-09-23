<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\AppVersion;

use App\Enums\AppPlatform;
use App\Enums\ReleaseStatus;
use App\Models\App\AppVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 版本保存请求（创建/编辑）
 *
 * @property-read string $platform 平台
 * @property-read string $version 语义化版本号
 * @property-read int $build 构建号
 * @property-read string $download_url 下载地址
 * @property-read string|null $release_notes 更新说明
 * @property-read string|null $min_supported_version 最低支持版本
 * @property-read int $min_supported_build 最低支持构建号
 * @property-read bool $force_update 是否强制更新
 * @property-read int|null $file_size 安装包大小
 * @property-read string|null $file_hash 文件哈希
 * @property-read string $status 发布状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppVersionSaveRequest extends FormRequest
{
    /**
     * 注入 app_release_id（来自路由参数）和 created_by（当前管理员），
     * 让控制器可以直接 validated() 后写入模型。
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'app_release_id' => (int) $this->route('app'),
            'created_by' => $this->user('admin')?->id,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $versionId = $this->route('version');
        $appId = $this->route('app');

        return [
            'platform' => ['required', Rule::in(AppPlatform::values())],
            'version' => ['required', 'string', 'max:50', 'regex:/^\d+\.\d+\.\d+/'],
            'build' => [
                'required', 'integer', 'min:1',
                Rule::unique(AppVersion::class, 'build')
                    ->where('app_release_id', $appId instanceof \App\Models\App\AppRelease ? $appId->id : (int) $appId)
                    ->where('platform', $this->input('platform'))
                    ->ignore($versionId),
            ],
            'download_url' => ['required', 'string', 'max:500'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'min_supported_version' => ['nullable', 'string', 'max:50', 'regex:/^\d+\.\d+\.\d+/'],
            'min_supported_build' => ['sometimes', 'integer', 'min:0'],
            'force_update' => ['sometimes', 'boolean'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'file_hash' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', Rule::in(ReleaseStatus::values())],
            'app_release_id' => ['required', 'integer'],
            'created_by' => ['nullable', 'integer'],
        ];
    }
}
