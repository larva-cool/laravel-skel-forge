<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\AuditLog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 审计日志列表请求
 *
 * @property-read int|null $operator_id 操作人ID
 * @property-read string|null $operator_name 操作人名称关键词
 * @property-read string|null $route 路由名称关键词
 * @property-read string|null $method 请求方法
 * @property-read string|null $uri 请求路径关键词
 * @property-read int|null $status_code 响应状态码
 * @property-read bool|null $is_failed 是否仅看失败操作
 * @property-read string|null $start_date 操作时间起始
 * @property-read string|null $end_date 操作时间截止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AuditLogIndexRequest extends FormRequest
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
            'operator_id' => ['nullable', 'integer', 'min:1'],
            'operator_name' => ['nullable', 'string', 'max:64'],
            'route' => ['nullable', 'string', 'max:128'],
            'method' => ['nullable', Rule::in(['POST', 'PUT', 'PATCH', 'DELETE'])],
            'uri' => ['nullable', 'string', 'max:255'],
            'status_code' => ['nullable', 'integer', 'min:100', 'max:599'],
            'is_failed' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * 归一化请求方法为大写
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('method')) {
            $this->merge(['method' => strtoupper((string) $this->input('method'))]);
        }
    }

    /**
     * 获取列表筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'operator_id', 'operator_name', 'route', 'method',
            'uri', 'status_code', 'is_failed', 'start_date', 'end_date',
        ]);
    }
}
