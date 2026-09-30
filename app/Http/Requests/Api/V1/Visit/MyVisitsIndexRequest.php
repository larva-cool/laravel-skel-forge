<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Visit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 我的访问列表查询条件
 *
 * 全局分页参数 page / per_page 已由 Apifox 项目预置，此处仅定义业务过滤项。
 */
class MyVisitsIndexRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'source_type' => ['nullable', 'string', Rule::in(source_types())],
        ];
    }
}
