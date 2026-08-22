<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\AppRelease;

use App\Models\App\AppRelease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 应用保存请求（创建/编辑）
 *
 * @property-read string $name 应用名称
 * @property-read string $slug 应用唯一标识
 * @property-read string|null $description 应用描述
 * @property-read string|null $icon 应用图标URL
 * @property-read int $sort 排序权重
 * @property-read bool $status 状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppReleaseSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $appId = $this->route('app');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/', Rule::unique(AppRelease::class, 'slug')->ignore($appId)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:500'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
