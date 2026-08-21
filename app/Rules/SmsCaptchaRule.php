<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Rules;

use App\Services\SmsCaptchaService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 短信验证码检测规则
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SmsCaptchaRule implements DataAwareRule, ValidationRule
{
    /**
     * The client ip.
     */
    public string $phoneField;

    /**
     * The client ip.
     */
    public ?string $clientIp;

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
    public function __construct(string $phoneField, ?string $clientIp = null)
    {
        $this->phoneField = $phoneField;
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
        $phone = $this->data[$this->phoneField] ?? 'verify_phone';
        // 为$clientIp提供默认值
        $ip = $this->clientIp ?? '127.0.0.1';
        $service = SmsCaptchaService::make($phone, $ip);
        if (app()->environment(['testing'])) {
            $service->setFixedVerifyCode('123456');
        }
        if (! $service->validate($value, false)) {
            $fail('validation.verify_code')->translate();
        }
    }
}
