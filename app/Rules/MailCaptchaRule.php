<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Rules;

use App\Services\MailCaptchaService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 邮件验证码检测
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MailCaptchaRule implements DataAwareRule, ValidationRule
{
    /**
     * The client ip.
     */
    public string $emailField;

    /**
     * The client ip.
     */
    public string $clientIp;

    /**
     * 正在验证的所有数据。
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $emailField, $clientIp)
    {
        $this->emailField = $emailField;
        $this->clientIp = $clientIp;
    }

    /**
     * 设置正在验证的数据。
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = $this->data[$this->emailField] ?? 'verify_email';

        $service = MailCaptchaService::make($email, $this->clientIp);
        if (app()->environment(['testing'])) {
            $service->setFixedVerifyCode('123456');
        }
        if (! $service->validate($value, false)) {
            $fail('validation.verify_code')->translate();
        }
    }
}
