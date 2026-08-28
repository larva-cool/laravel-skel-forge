<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\UserSegment;

use App\Enums\SegmentType;
use App\Services\Segment\SegmentRuleEvaluator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 分群保存请求（创建/编辑）
 *
 * 按项目规范，不引用任何数据模型 Schema（内联校验）。
 * 规则分群要求 code 必填且唯一；手动分群 code 可空。
 *
 * @property-read string|null $code
 * @property-read string $name
 * @property-read string $label_type
 * @property-read int $type
 * @property-read string|null $group
 * @property-read string|null $description
 * @property-read int $sort
 * @property-read bool $status
 * @property-read array<string,mixed>|null $rule
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentSaveRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $segmentId = $this->route('segment')?->id;
        $isRule = $this->input('type') === SegmentType::RULE->value;

        return [
            'code' => [
                Rule::requiredIf($isRule),
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('user_segments', 'code')->ignore($segmentId)->whereNotNull('code'),
            ],
            'name' => ['required', 'string', 'max:64'],
            'label_type' => ['sometimes', 'string', 'in:default,primary,success,warning,danger,info'],
            'type' => ['required', 'integer', Rule::in([SegmentType::MANUAL->value, SegmentType::RULE->value])],
            'group' => ['sometimes', 'nullable', 'string', 'max:32'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
            'rule' => ['required_if:type,2', 'nullable', 'array'],
        ];
    }

    /**
     * 进一步校验 rule 字段（结构 + 字段/操作符兼容性）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $rule = $this->input('rule');
            if (! is_array($rule)) {
                return;
            }
            // 编辑场景：若为规则分群，不允许改 rule（业务约束放控制器；这里只做结构校验）
            try {
                (new SegmentRuleEvaluator)->validate($rule);
            } catch (\InvalidArgumentException $e) {
                $v->errors()->add('rule', $e->getMessage());
            }
        });
    }
}
