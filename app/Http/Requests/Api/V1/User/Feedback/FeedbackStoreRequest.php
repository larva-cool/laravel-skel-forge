<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User\Feedback;

use App\Models\System\Dict;
use App\Rules\SensitiveTextRule;
use App\Services\SensitiveWordService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 用户提交反馈请求
 *
 * @property-read string $title 标题
 * @property-read string $content 详细描述
 * @property-read array<int, string>|null $images 反馈图片URL列表
 * @property-read string $type 反馈类型（字典项 value）
 * @property-read string|null $contact_name 联系人
 * @property-read string|null $contact_info 联系方式
 * @property-read string|null $user_agent 浏览器UA（自动注入）
 * @property-read string|null $ip_address 提交IP（自动注入）
 * @property-read int|null $user_id 登录用户ID（自动注入）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackStoreRequest extends FormRequest
{
    /**
     * 注入 user_agent / ip_address / user_id 上下文字段，
     * 让控制器只需调用 validated() 即可获得完整数据。
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_agent' => $this->userAgent(),
            'ip_address' => $this->ip(),
            'user_id' => $this->user()?->id,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $typeValues = Dict::query()
            ->where('code', 'feedback_type')
            ->where('status', 1)
            ->with(['dictItems' => fn ($q) => $q->where('status', 1)])
            ->first()
            ?->dictItems
            ->pluck('value')
            ->toArray() ?? [];

        return [
            'title' => ['required', 'string', 'max:200', new SensitiveTextRule],
            'content' => ['required', 'string', 'max:5000', new SensitiveTextRule],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['required', 'string', 'url', 'max:500'],
            'type' => ['required', 'string', 'max:50', Rule::in($typeValues)],
            'contact_name' => ['nullable', 'string', 'max:50'],
            'contact_info' => ['nullable', 'string', 'max:100'],
            'user_agent' => ['nullable', 'string', 'max:500'],
            'ip_address' => ['nullable', 'ip'],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * 校验通过后对 title/content 做敏感词替换（反馈默认走人工审核，无需标记状态）
     */
    protected function passedValidation(): void
    {
        $service = app(SensitiveWordService::class);
        $titleResult = $service->detect((string) $this->input('title'));
        $contentResult = $service->detect((string) $this->input('content'));

        $this->merge([
            'title' => $titleResult->filtered,
            'content' => $contentResult->filtered,
        ]);

        $allHits = array_merge($titleResult->hitWords(), $contentResult->hitWords());
        if ($allHits !== []) {
            try {
                $service->incrementHitCount(array_unique($allHits));
            } catch (\Throwable) {
            }
        }
    }
}
