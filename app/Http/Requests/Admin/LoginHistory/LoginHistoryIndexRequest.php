<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\LoginHistory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * 全局登录历史列表请求
 *
 * @property-read string|null $guard 归属主体类型（user/admin）
 * @property-read int|null $user_id 归属主体ID
 * @property-read string|null $keyword 关键词（地址、设备、平台、浏览器）
 * @property-read string|null $ip 登录IP
 * @property-read bool|null $result 登录结果
 * @property-read string|null $login_start 登录时间起始
 * @property-read string|null $login_end 登录时间截止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginHistoryIndexRequest extends FormRequest
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
            'guard' => ['nullable', Rule::in(array_keys($this->morphMap()))],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'ip' => ['nullable', 'string', 'max:45'],
            'result' => ['nullable', 'boolean'],
            'login_start' => ['nullable', 'date'],
            'login_end' => ['nullable', 'date', 'after_or_equal:login_start'],
        ];
    }

    /**
     * 获取列表筛选条件
     *
     * 将 guard 别名转换为模型 morph 别名，供 LoginHistory::scopeFilter 使用。
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->only(['user_id', 'keyword', 'ip', 'result', 'login_start', 'login_end']);

        if ($guard = $this->string('guard')->toString()) {
            $filters['user_type'] = $guard;
        }

        return $filters;
    }

    /**
     * 可用的归属主体类型映射
     *
     * @return array<string, class-string>
     */
    protected function morphMap(): array
    {
        return array_intersect_key(
            Config::array('morph_maps'),
            ['user' => true, 'admin' => true],
        );
    }
}
