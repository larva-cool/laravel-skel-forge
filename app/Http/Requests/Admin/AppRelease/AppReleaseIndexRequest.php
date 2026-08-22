<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\AppRelease;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 应用列表请求
 *
 * @property-read string|null $keyword 关键词
 * @property-read bool|null $status 状态
 * @property-read string|null $start_date 创建时间起始
 * @property-read string|null $end_date 创建时间截止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppReleaseIndexRequest extends FormRequest
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
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'boolean'],
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
        return $this->only(['keyword', 'status', 'start_date', 'end_date']);
    }
}
