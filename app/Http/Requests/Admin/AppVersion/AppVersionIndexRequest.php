<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\AppVersion;

use App\Enums\AppPlatform;
use App\Enums\ReleaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 版本列表请求
 *
 * @property-read string|null $platform 平台
 * @property-read string|null $status 发布状态
 * @property-read string|null $version 版本号关键词
 * @property-read bool|null $force_update 是否强制更新
 * @property-read string|null $start_date 创建时间起始
 * @property-read string|null $end_date 创建时间截止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppVersionIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'platform' => ['nullable', Rule::in(AppPlatform::values())],
            'status' => ['nullable', Rule::in(ReleaseStatus::values())],
            'version' => ['nullable', 'string', 'max:50'],
            'force_update' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * 获取列表筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only(['platform', 'status', 'version', 'force_update', 'start_date', 'end_date']);
    }
}
