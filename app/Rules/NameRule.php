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
 * 昵称、姓名验证规则
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class NameRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value)) {
            $fail('validation.username')->translate();

            return;
        }
        $value = (string) $value;
        if (! preg_match('/^[-a-zA-Z0-9_\x{4e00}-\x{9fa5}.@]+$/u', $value)) {
            $fail('validation.username')->translate();
        }
    }
}
