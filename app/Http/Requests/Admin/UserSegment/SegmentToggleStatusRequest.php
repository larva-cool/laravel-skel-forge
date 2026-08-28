<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\UserSegment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 启用/停用切换请求（无 body，仅作权限校验用）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentToggleStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
