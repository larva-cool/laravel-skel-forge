<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 手机号码验证规则
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PhoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value)) {
            $fail('validation.phone')->translate();

            return;
        }
        $value = (string) $value;
        if (! preg_match('/^1[2-9]\d{9}$/', $value)) {
            $fail('validation.phone')->translate();
        }
    }
}
