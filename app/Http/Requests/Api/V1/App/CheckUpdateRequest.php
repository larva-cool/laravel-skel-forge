<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\App;

use App\Enums\AppPlatform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 检查版本更新请求
 *
 * @property-read string $slug 应用唯一标识
 * @property-read string $platform 平台
 * @property-read string $version 当前版本号
 * @property-read int $build 当前构建号
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CheckUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100'],
            'platform' => ['required', Rule::in(AppPlatform::values())],
            'version' => ['required', 'string', 'max:50'],
            'build' => ['required', 'integer', 'min:1'],
        ];
    }
}
