<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Content\Comment;

use App\Enums\ReviewStatus;
use App\Enums\SensitiveLevel;
use App\Rules\SensitiveTextRule;
use App\Services\SensitiveWordService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 发布评论
 *
 * @property-read int $user_id 用户ID
 * @property-read string $source_type 评论目标类型
 * @property-read int $source_id 评论目标ID
 * @property-read string $content 评论内容（经过敏感词替换后的值）
 * @property-read ReviewStatus $status 评论审核状态
 * @property-read int|null $parent_id 父评论ID
 * @property-read array<int, int>|null $mentioned_users 艾特用户ID列表
 */
class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
            'ip_address' => $this->ip(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['numeric'],
            'source_type' => ['required', 'string', Rule::in(source_types())],
            'source_id' => ['required', 'integer', 'min:1'],
            'content' => ['required', 'string', 'min:1', 'max:1000', new SensitiveTextRule],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'mentioned_users' => ['nullable', 'array', 'max:10'],
            'mentioned_users.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * 校验通过后对内容做敏感词处理：
     *   - replace 级别：自动替换敏感词为掩码
     *   - review 级别：将评论状态置为待审核
     */
    protected function passedValidation(): void
    {
        $service = app(SensitiveWordService::class);
        $result = $service->detect((string) $this->input('content'));

        $this->merge([
            'content' => $result->filtered,
            'status' => $result->maxLevel === SensitiveLevel::REVIEW
                ? ReviewStatus::PENDING
                : ReviewStatus::APPROVED,
        ]);

        if ($result->hits !== []) {
            try {
                $service->incrementHitCount($result->hitWords());
            } catch (\Throwable) {
            }
        }
    }
}
