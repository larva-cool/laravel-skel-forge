<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Rules;

use App\Support\IDCardHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 中国公民身份证号验证规则
 *
 * 基于 {@see IDCardHelper::validate()} 实现 GB 11643-1999 校验位算法：
 *  - 前 17 位必须为纯数字
 *  - 第 18 位为 0-9 或 X/x
 *  - 加权因子计算后的校验位必须匹配
 *
 * 空值不校验（由 required / required_if 等规则负责）。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdCardRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_scalar($value) || ! IDCardHelper::validate((string) $value)) {
            $fail('validation.id_card')->translate();
        }
    }
}
