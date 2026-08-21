<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Mail\MailVerifyCode;
use App\Models\System\MailCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * 邮件验证码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class MailCaptchaService
{
    /**
     * 两次获取验证码的等待时间
     */
    protected int $waitTime;

    /**
     * 验证码有效期
     */
    protected int $duration;

    /**
     * 验证码长度
     */
    protected int $length;

    /**
     * 允许尝试验证的次数
     */
    protected int $testLimit;

    /**
     * @var string 邮件地址
     */
    protected string $email;

    /**
     * 静止验证码 功能测试时生成静止验证码
     */
    protected ?string $fixedVerifyCode;

    /**
     * @var string 请求的IP
     */
    protected string $ip;

    /**
     * 缓存 Key
     */
    private string $cacheKey;

    /**
     * MailVerifyCodeService constructor.
     */
    public function __construct(string $email, string $ip)
    {
        $this->email = $email;
        $this->ip = $ip;
        $this->duration = (int) settings('email_captcha.duration', 10);
        $this->length = (int) settings('email_captcha.length', 6);
        $this->waitTime = (int) settings('email_captcha.wait_time', 60);
        $this->testLimit = (int) settings('email_captcha.test_limit', 3);
        $this->cacheKey = 'email_captcha:'.$email;
        $this->fixedVerifyCode = null;
    }

    /**
     * 创建实例
     */
    public static function make(string $email, string $ip): MailCaptchaService
    {
        return new static($email, $ip);
    }

    /**
     * 发送验证码
     */
    public function send(): array
    {
        if (RateLimiter::tooManyAttempts($this->cacheKey, 1)) {
            $waitTime = RateLimiter::availableIn($this->cacheKey);
            $verifyCode = $this->getVerifyCode();
            $data = [
                'hash' => $this->generateValidationHash($verifyCode),
                'wait_time' => $waitTime,
                'email' => $this->email,
            ];
        } else {
            RateLimiter::increment($this->cacheKey);
            $verifyCode = $this->getVerifyCode(true);
            if (! app()->environment(['local', 'testing'])) {
                Mail::to($this->email)->send(new MailVerifyCode($verifyCode));
            }
            MailCode::build($this->email, $this->ip, $verifyCode);
            $data = [
                'hash' => $this->generateValidationHash($verifyCode),
                'wait_time' => $this->waitTime,
                'email' => $this->email,
            ];
        }
        $data['verify_code'] = '';
        if (! app()->environment('production')) {
            $data['verify_code'] = $verifyCode;
        }

        return $data;
    }

    /**
     * 获取验证码
     *
     * @param  bool  $regenerate  是否重新生成验证码
     * @return string 验证码
     */
    public function getVerifyCode(bool $regenerate = false): string
    {
        if (! is_null($this->fixedVerifyCode)) {
            return $this->fixedVerifyCode;
        }
        $verifyCode = MailCode::query()->where('email', $this->email)->where('state', 0)->orderBy('send_at',
            'desc')->value('code');
        if ($verifyCode === null || $regenerate) {
            $verifyCode = Str::password($this->length, false, true, false);
        }

        return $verifyCode;
    }

    /**
     * 验证输入，看看它是否与生成的代码相匹配
     *
     * @param  string|int  $input  user input
     * @param  bool  $caseSensitive  whether the comparison should be case-sensitive
     * @return bool whether the input is valid
     */
    public function validate($input, bool $caseSensitive): bool
    {
        if (! is_null($this->fixedVerifyCode)) {
            return $caseSensitive ? ($input === $this->fixedVerifyCode) : strcasecmp($input,
                $this->fixedVerifyCode) === 0;
        }
        $model = MailCode::getCode($this->email);
        if ($model === null) {
            return false;
        }
        $valid = $model->validate($input, $caseSensitive);
        if ($valid || $model->verify_count > $this->testLimit && $this->testLimit > 0) {
            RateLimiter::clear($this->cacheKey);
        }

        return $valid;
    }

    /**
     * 生成一个可以用于客户端验证的哈希。
     *
     * @param  string  $code  验证码
     * @return string 用户客户端验证的哈希码
     */
    public function generateValidationHash(string $code): string
    {
        for ($h = 0, $i = strlen($code) - 1; $i >= 0; $i--) {
            $h += intval($code[$i]);
        }

        return (string) $h;
    }

    /**
     * 获取今日IP地址的发送次数
     */
    public function getIpSendCount(): int
    {
        return MailCode::getIpTodayCount($this->ip);
    }

    /**
     * 获取今日Email发送次数
     */
    public function getMailSendCount(): int
    {
        return MailCode::getMailTodayCount($this->email);
    }

    /**
     * 获取今日总发送次数
     */
    public function getSendCount(): int
    {
        return MailCode::getTodayCount($this->email, $this->ip);
    }

    /**
     * 设置验证码的测试限制
     *
     * @return $this
     */
    public function setTestLimit(int $testLimit): MailCaptchaService
    {
        $this->testLimit = $testLimit;

        return $this;
    }

    /**
     * 获取验证码的测试限制
     */
    public function getTestLimit(): int
    {
        return $this->testLimit;
    }

    /**
     * 设置两次获取验证码的等待时间
     *
     * @return $this
     */
    public function setWaitTime(int $waitTime): MailCaptchaService
    {
        $this->waitTime = $waitTime;

        return $this;
    }

    /**
     * 获取两次获取验证码的等待时间
     */
    public function getWaitTime(): int
    {
        return $this->waitTime;
    }

    /**
     * 设置验证码有效期
     *
     * @param  int  $duration  单位分钟
     * @return $this
     */
    public function setDuration(int $duration): MailCaptchaService
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * 获取验证码有效期
     */
    public function getDuration(): int
    {
        return $this->duration;
    }

    /**
     * 设置验证码长度
     *
     * @return $this
     */
    public function setLength(int $length): MailCaptchaService
    {
        $this->length = $length;

        return $this;
    }

    /**
     * 获取验证码长度
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * 设置请求的IP地址
     *
     * @return $this
     */
    public function setIp(string $ip): MailCaptchaService
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * 获取请求的IP地址
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * 设置静态验证码
     */
    public function setFixedVerifyCode(string $code): MailCaptchaService
    {
        $this->fixedVerifyCode = $code;

        return $this;
    }

    /**
     * 获取静态验证码
     */
    public function getFixedVerifyCode(): ?string
    {
        return $this->fixedVerifyCode;
    }
}
