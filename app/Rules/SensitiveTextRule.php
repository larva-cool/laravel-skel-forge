<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Rules;

use App\Services\SensitiveWordService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 敏感文本校验规则
 *
 * 使用方式：
 *   new SensitiveTextRule()             // 默认：仅拦截 block 级别词
 *   new SensitiveTextRule('strict')     // 严格模式：拦截所有级别（replace/review/block）
 *   new SensitiveTextRule('block', true) // 拦截 block，并自动累加命中次数
 */
class SensitiveTextRule implements ValidationRule
{
    /**
     * @param  string  $mode  block=仅拦截 block 级别；strict=命中任意级别都拦截
     * @param  bool  $incrementHit  是否累加命中次数（默认 true）
     */
    public function __construct(
        protected string $mode = 'block',
        protected bool $incrementHit = true,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value)) {
            $fail('validation.sensitive_text')->translate();

            return;
        }

        $text = (string) $value;
        if ($text === '') {
            return;
        }

        $service = app(SensitiveWordService::class);
        $result = $service->detect($text);

        if ($result->hits === []) {
            return;
        }

        // 判定是否拦截
        $shouldBlock = match ($this->mode) {
            'strict' => $result->hits !== [],
            default => $result->isBlocked(),
        };

        if (! $shouldBlock) {
            // replace/review 级别交由上层调用 detect() 自行处理（替换文本/标记待审核）
            return;
        }

        // 累加命中次数
        if ($this->incrementHit) {
            try {
                $service->incrementHitCount($result->hitWords());
            } catch (\Throwable) {
                // 命中次数统计失败不影响主流程
            }
        }

        $words = implode('、', array_slice($result->hitWords(), 0, 3));
        $fail('validation.sensitive_text')->translate(['words' => $words]);
    }
}
