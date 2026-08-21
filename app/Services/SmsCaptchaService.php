<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Models\System\PhoneCode;
use App\Sms\VerifyCodeMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;

/**
 * 手机验证码
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class SmsCaptchaService
{
    protected string|int $phone;

    /**
     * 两次获取验证码的等待时间
     */
    protected int $waitTime;

    /**
     * 验证码有效期
     */
    protected int $duration;

    /**
     * 最长长度
     */
    protected int $length;

    /**
     * 静止验证码 功能测试时生成静止验证码
     */
    protected ?string $fixedVerifyCode;

    /**
     * 允许尝试验证的次数
     */
    protected int $testLimit;

    /**
     * 请求的IP
     */
    protected string $ip;

    /**
     * 验证码使用场景
     */
    protected string $scene;

    /**
     * 缓存 Key
     */
    private string $cacheKey;

    /**
     * Constructor.
     */
    public function __construct(int|string $phone, string $ip, string $scene = 'default')
    {
        $this->phone = $phone;
        $this->ip = $ip;
        $this->scene = $scene;
        $this->duration = (int) settings('sms_captcha.duration', 10);
        $this->length = (int) settings('sms_captcha.length', 6);
        $this->waitTime = (int) settings('sms_captcha.wait_time', 60);
        $this->testLimit = (int) settings('sms_captcha.test_limit', 3);
        $this->cacheKey = 'sms_captcha:'.$phone;
        $this->fixedVerifyCode = null;
    }

    /**
     * 创建实例
     */
    public static function make(int|string $phone, string $ip, ?string $scene = null): SmsCaptchaService
    {
        $scene = $scene ?? 'default';

        return new static($phone, $ip, $scene);
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
                'phone' => $this->phone,
                'scene' => $this->scene,
            ];
        } else {
            RateLimiter::increment($this->cacheKey);
            $verifyCode = $this->getVerifyCode(true);
            if (! app()->environment('local', 'testing')) {// 生产环境才会发送
                try {
                    sms()->send($this->phone, new VerifyCodeMessage([
                        'code' => $verifyCode,
                        'duration' => $this->duration,
                        'scene' => $this->scene,
                    ]));
                } catch (NoGatewayAvailableException $exception) {
                    foreach ($exception->getExceptions() as $e) {
                        Log::error($e->getMessage());
                    }
                } catch (\Exception $exception) {
                    Log::error($exception->getMessage());
                }
            }
            PhoneCode::build($this->phone, $this->ip, $verifyCode, $this->scene);
            $data = [
                'hash' => $this->generateValidationHash($verifyCode),
                'wait_time' => $this->waitTime,
                'phone' => $this->phone,
                'scene' => $this->scene,
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
        $verifyCode = PhoneCode::query()->where('phone', $this->phone)->where('state', 0)->orderBy('send_at',
            'desc')->value('code');
        if ($verifyCode === null || $regenerate) {
            $verifyCode = Str::password($this->length, false, true, false, false);
        }

        return $verifyCode;
    }

    /**
     * 验证输入，看看它是否与生成的代码相匹配
     *
     * @param  int|string  $input  user input
     * @param  bool  $caseSensitive  whether the comparison should be case-sensitive
     * @return bool whether the input is valid
     */
    public function validate(int|string $input, bool $caseSensitive): bool
    {
        if (! is_null($this->fixedVerifyCode)) {
            return $caseSensitive ? ($input === $this->fixedVerifyCode) : strcasecmp($input,
                $this->fixedVerifyCode) === 0;
        }
        $model = PhoneCode::getCode($this->phone);
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
     * 获取IP地址的发送次数
     */
    public function getIpSendCount(): int
    {
        return PhoneCode::getIpHourCount($this->ip);
    }

    /**
     * 获取手机号发送次数
     */
    public function getPhoneSendCount(): int
    {
        return PhoneCode::getPhoneHourCount($this->phone);
    }

    /**
     * 获取总发送次数
     */
    public function getSendCount(): int
    {
        return $this->getPhoneSendCount() + $this->getIpSendCount();
    }

    /**
     * 设置验证码的测试限制
     *
     * @return $this
     */
    public function setTestLimit(int $testLimit): SmsCaptchaService
    {
        $this->testLimit = $testLimit;

        return $this;
    }

    /**
     * 设置验证场景
     *
     * @return $this
     */
    public function setScene(string $scene): SmsCaptchaService
    {
        $this->scene = $scene;

        return $this;
    }

    /**
     * 设置两次获取验证码的等待时间
     *
     * @return $this
     */
    public function setWaitTime(int $waitTime): SmsCaptchaService
    {
        $this->waitTime = $waitTime;

        return $this;
    }

    /**
     * 设置验证码有效期
     *
     * @param  int  $duration  单位分钟
     * @return $this
     */
    public function setDuration(int $duration): SmsCaptchaService
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * 设置验证码长度
     *
     * @return $this
     */
    public function setLength(int $length): SmsCaptchaService
    {
        $this->length = $length;

        return $this;
    }

    /**
     * 设置请求的IP地址
     *
     * @return $this
     */
    public function setIp(string $ip): SmsCaptchaService
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * 设置静态验证码
     *
     * @return $this
     */
    public function setFixedVerifyCode(string $code): SmsCaptchaService
    {
        $this->fixedVerifyCode = $code;

        return $this;
    }
}
