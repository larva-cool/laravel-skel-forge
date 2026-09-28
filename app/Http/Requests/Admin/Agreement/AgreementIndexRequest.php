<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Agreement;

use App\Enums\AgreementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 协议列表筛选请求
 *
 * @property-read string|null $keyword 关键词搜索
 * @property-read string|null $category 协议类别筛选
 * @property-read string|null $status 状态筛选
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', Rule::enum(AgreementStatus::class)],
        ];
    }
}
