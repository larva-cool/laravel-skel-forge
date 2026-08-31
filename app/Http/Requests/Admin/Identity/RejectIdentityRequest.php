<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Identity;

use App\Models\System\Dict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 实名认证驳回请求（管理端）
 *
 * @property-read string $reason 驳回原因
 * @property-read string|null $remark 驳回备注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RejectIdentityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reasonValues = Dict::query()
            ->where('code', 'identity_reject_reason')
            ->where('status', 1)
            ->with(['dictItems' => fn ($q) => $q->where('status', 1)])
            ->first()
            ?->dictItems
            ->pluck('value')
            ->toArray() ?? [];

        return [
            'reason' => ['required', 'string', Rule::in($reasonValues)],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }
}
