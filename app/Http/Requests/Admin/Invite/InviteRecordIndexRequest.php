<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Invite;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * 邀请记录列表请求
 *
 * @property-read string|null $inviter_type 邀请人类型（user/admin）
 * @property-read int|null $inviter_id 邀请人ID
 * @property-read string|null $invitee_type 被邀请人类型（user/admin）
 * @property-read int|null $invitee_id 被邀请人ID
 * @property-read string|null $description 描述关键词
 * @property-read string|null $created_start 邀请时间起始
 * @property-read string|null $created_end 邀请时间截止
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRecordIndexRequest extends FormRequest
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
            'inviter_type' => ['nullable', Rule::in(array_keys($this->morphMap()))],
            'inviter_id' => ['nullable', 'integer', 'min:1'],
            'invitee_type' => ['nullable', Rule::in(array_keys($this->morphMap()))],
            'invitee_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:100'],
            'created_start' => ['nullable', 'date'],
            'created_end' => ['nullable', 'date', 'after_or_equal:created_start'],
        ];
    }

    /**
     * 获取列表筛选条件
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'inviter_type',
            'inviter_id',
            'invitee_type',
            'invitee_id',
            'description',
            'created_start',
            'created_end',
        ]);
    }

    /**
     * 可用的邀请主体类型映射
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
