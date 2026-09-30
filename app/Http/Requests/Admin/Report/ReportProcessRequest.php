<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 举报处理请求（管理端）
 *
 * @property-read string $status 处理状态（resolved|rejected）
 * @property-read string|null $remark 处理备注
 * @property-read array<int, array<string, mixed>>|null $actions 联动处置动作
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportProcessRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ReportStatus::class)],
            'remark' => ['nullable', 'string', 'max:500'],
            'actions' => ['nullable', 'array'],
            'actions.*.type' => ['required_with:actions', 'string', 'in:hide_comment,delete_comment,freeze_user'],
            'actions.*.duration' => ['nullable', 'integer', 'min:0', 'max:365'],
            'actions.*.reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
