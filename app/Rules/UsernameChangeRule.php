<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 用户名修改检测
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UsernameChangeRule implements ValidationRule
{
    /**
     * The user.
     */
    public User $user;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowedNumber = (int) settings('user.username_change', 3);
        if ($this->user->extra->username_change_count >= $allowedNumber) {
            $fail('validation.custom.username.change_count')->translate();
        }
    }
}
