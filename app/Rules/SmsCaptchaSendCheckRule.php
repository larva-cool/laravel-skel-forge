<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Rules;

use App\Services\SmsCaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 检测手机号是否有权发送验证码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SmsCaptchaSendCheckRule implements ValidationRule
{
    /**
     * The client ip.
     */
    public string $clientIp;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $clientIp)
    {
        $this->clientIp = $clientIp;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $verifyCode = SmsCaptchaService::make($value, $this->clientIp);
        if (app()->environment(['testing'])) {
            return;
        }
        // 一个IP地址每小时最多发送 20
        if ($verifyCode->getIpSendCount() > settings('sms_captcha.ip_count', 20)) {
            $fail('validation.custom.phone.sms_captcha_send_check')->translate();
        }
        // 一个手机号码每小时最多发送 10条
        if ($verifyCode->getPhoneSendCount() > settings('sms_captcha.phone_count', 10)) {
            $fail('validation.custom.phone.sms_captcha_send_check')->translate();
        }
    }
}
