<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\System\MailCode;
use App\Services\MailCaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * MailCaptchaService 单元测试
 */
#[CoversClass(MailCaptchaService::class)]
#[Group('services')]
#[Group('mail-captcha')]
class MailCaptchaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MailCaptchaService $service;

    private string $email = 'test@example.com';

    private string $ip = '127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MailCaptchaService($this->email, $this->ip);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('email_captcha:'.$this->email);
        parent::tearDown();
    }

    #[Test]
    #[TestDox('make 创建实例返回正确类型')]
    public function make_returns_instance(): void
    {
        $service = MailCaptchaService::make($this->email, $this->ip);

        $this->assertInstanceOf(MailCaptchaService::class, $service);
    }

    #[Test]
    #[TestDox('send 首次发送生成验证码并写入数据库')]
    public function send_creates_mail_code_record(): void
    {
        $data = $this->service->send();

        $this->assertDatabaseHas('mail_codes', [
            'email' => $this->email,
            'ip' => $this->ip,
        ]);
        $this->assertArrayHasKey('hash', $data);
        $this->assertArrayHasKey('wait_time', $data);
        $this->assertArrayHasKey('email', $data);
    }

    #[Test]
    #[TestDox('send 在非生产环境返回 verify_code')]
    public function send_returns_verify_code_in_testing(): void
    {
        $data = $this->service->send();

        $this->assertNotEmpty($data['verify_code']);
    }

    #[Test]
    #[TestDox('send 返回的 hash 是验证码各位数字之和')]
    public function send_returns_correct_hash(): void
    {
        $this->service->setFixedVerifyCode('123456');
        $data = $this->service->send();

        // 1+2+3+4+5+6 = 21
        $this->assertSame('21', $data['hash']);
    }

    #[Test]
    #[TestDox('send 限速时返回 wait_time')]
    public function send_throttled_returns_wait_time(): void
    {
        // 第一次发送
        $this->service->send();

        // 第二次发送（被限速）
        $data = $this->service->send();

        $this->assertGreaterThan(0, $data['wait_time']);
    }

    #[Test]
    #[TestDox('getVerifyCode 有 fixedVerifyCode 时返回固定验证码')]
    public function get_verify_code_returns_fixed_code_when_set(): void
    {
        $this->service->setFixedVerifyCode('999999');

        $this->assertSame('999999', $this->service->getVerifyCode());
        $this->assertSame('999999', $this->service->getVerifyCode(true));
    }

    #[Test]
    #[TestDox('getVerifyCode 无记录时生成指定长度验证码')]
    public function get_verify_code_generates_code_when_no_record(): void
    {
        $code = $this->service->getVerifyCode();

        $this->assertSame(6, strlen($code));
    }

    #[Test]
    #[TestDox('getVerifyCode 有记录且不重新生成时返回数据库中的验证码')]
    public function get_verify_code_returns_existing_code(): void
    {
        MailCode::create([
            'email' => $this->email,
            'code' => '654321',
            'ip' => $this->ip,
            'state' => 0,
        ]);

        $code = $this->service->getVerifyCode();

        $this->assertSame('654321', $code);
    }

    #[Test]
    #[TestDox('getVerifyCode regenerate=true 时生成新验证码')]
    public function get_verify_code_regenerates_when_requested(): void
    {
        MailCode::create([
            'email' => $this->email,
            'code' => '654321',
            'ip' => $this->ip,
            'state' => 0,
        ]);

        $code = $this->service->getVerifyCode(true);

        $this->assertNotSame('654321', $code);
    }

    #[Test]
    #[TestDox('validate fixedVerifyCode 模式下正确匹配')]
    public function validate_with_fixed_code_correct_match(): void
    {
        $this->service->setFixedVerifyCode('123456');

        $this->assertTrue($this->service->validate('123456', true));
    }

    #[Test]
    #[TestDox('validate fixedVerifyCode 模式下错误匹配')]
    public function validate_with_fixed_code_wrong_match(): void
    {
        $this->service->setFixedVerifyCode('123456');

        $this->assertFalse($this->service->validate('654321', true));
    }

    #[Test]
    #[TestDox('validate fixedVerifyCode 不区分大小写匹配')]
    public function validate_with_fixed_code_case_insensitive(): void
    {
        $this->service->setFixedVerifyCode('ABC123');

        $this->assertTrue($this->service->validate('abc123', false));
    }

    #[Test]
    #[TestDox('validate 无验证码记录时返回 false')]
    public function validate_returns_false_when_no_code_exists(): void
    {
        $this->assertFalse($this->service->validate('123456', true));
    }

    #[Test]
    #[TestDox('validate 正确验证码返回 true 并标记已使用')]
    public function validate_correct_code_returns_true(): void
    {
        MailCode::create([
            'email' => $this->email,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => 0,
        ]);

        $this->assertTrue($this->service->validate('123456', true));
        $this->assertDatabaseHas('mail_codes', [
            'email' => $this->email,
            'state' => MailCode::USED_STATE,
        ]);
    }

    #[Test]
    #[TestDox('validate 错误验证码返回 false 并增加验证次数')]
    public function validate_wrong_code_returns_false_and_increments_count(): void
    {
        MailCode::create([
            'email' => $this->email,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => 0,
        ]);

        $this->assertFalse($this->service->validate('000000', true));
        $this->assertDatabaseHas('mail_codes', [
            'email' => $this->email,
            'verify_count' => 1,
        ]);
    }

    #[Test]
    #[TestDox('validate 已使用的验证码返回 false')]
    public function validate_used_code_returns_false(): void
    {
        MailCode::create([
            'email' => $this->email,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => MailCode::USED_STATE,
            'usage_at' => now(),
        ]);

        $this->assertFalse($this->service->validate('123456', true));
    }

    #[Test]
    #[TestDox('validate 超过测试限制时清除限速')]
    public function validate_clears_rate_limiter_when_exceeding_test_limit(): void
    {
        $this->service->setTestLimit(2);
        MailCode::create([
            'email' => $this->email,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => 0,
            'verify_count' => 3,
        ]);

        $this->assertFalse($this->service->validate('000000', true));

        $this->assertFalse(RateLimiter::tooManyAttempts('email_captcha:'.$this->email, 1));
    }

    #[Test]
    #[TestDox('generateValidationHash 返回验证码各位数字之和')]
    public function generate_validation_hash_returns_sum_of_digits(): void
    {
        $this->assertSame('21', $this->service->generateValidationHash('123456'));
        $this->assertSame('0', $this->service->generateValidationHash('000000'));
        $this->assertSame('54', $this->service->generateValidationHash('999999'));
    }

    #[Test]
    #[TestDox('getIpSendCount 返回 IP 今日发送次数')]
    public function get_ip_send_count_returns_count(): void
    {
        MailCode::create(['email' => 'other@example.com', 'code' => '111', 'ip' => $this->ip]);
        MailCode::create(['email' => 'another@example.com', 'code' => '222', 'ip' => $this->ip]);

        $this->assertSame(2, $this->service->getIpSendCount());
    }

    #[Test]
    #[TestDox('getMailSendCount 返回邮箱今日发送次数')]
    public function get_mail_send_count_returns_count(): void
    {
        MailCode::create(['email' => $this->email, 'code' => '111', 'ip' => '10.0.0.1']);
        MailCode::create(['email' => $this->email, 'code' => '222', 'ip' => '10.0.0.2']);

        $this->assertSame(2, $this->service->getMailSendCount());
    }

    #[Test]
    #[TestDox('getSendCount 返回邮箱和 IP 的今日总发送次数')]
    public function get_send_count_returns_combined_count(): void
    {
        // IP count: 2 (ip=127.0.0.1), Email count: 2 (email=test@example.com) → total = 4
        MailCode::create(['email' => $this->email, 'code' => '111', 'ip' => $this->ip]);
        MailCode::create(['email' => 'other@example.com', 'code' => '222', 'ip' => $this->ip]);
        MailCode::create(['email' => $this->email, 'code' => '333', 'ip' => '10.0.0.2']);

        $this->assertSame(4, $this->service->getSendCount());
    }

    #[Test]
    #[TestDox('setFixedVerifyCode 链式调用返回当前实例')]
    public function set_fixed_verify_code_returns_self(): void
    {
        $result = $this->service->setFixedVerifyCode('123456');

        $this->assertSame($this->service, $result);
    }

    #[Test]
    #[TestDox('setTestLimit 链式调用返回当前实例')]
    public function set_test_limit_returns_self(): void
    {
        $result = $this->service->setTestLimit(5);

        $this->assertSame($this->service, $result);
    }

    #[Test]
    #[TestDox('getTestLimit 返回当前测试限制')]
    public function get_test_limit_returns_value(): void
    {
        $this->service->setTestLimit(10);

        $this->assertSame(10, $this->service->getTestLimit());
    }

    #[Test]
    #[TestDox('setWaitTime / getWaitTime 链式调用')]
    public function set_and_get_wait_time(): void
    {
        $result = $this->service->setWaitTime(120);

        $this->assertSame($this->service, $result);
        $this->assertSame(120, $this->service->getWaitTime());
    }

    #[Test]
    #[TestDox('setDuration / getDuration 链式调用')]
    public function set_and_get_duration(): void
    {
        $result = $this->service->setDuration(30);

        $this->assertSame($this->service, $result);
        $this->assertSame(30, $this->service->getDuration());
    }

    #[Test]
    #[TestDox('setLength / getLength 链式调用')]
    public function set_and_get_length(): void
    {
        $result = $this->service->setLength(4);

        $this->assertSame($this->service, $result);
        $this->assertSame(4, $this->service->getLength());
    }

    #[Test]
    #[TestDox('setIp / getIp 链式调用')]
    public function set_and_get_ip(): void
    {
        $result = $this->service->setIp('10.0.0.1');

        $this->assertSame($this->service, $result);
        $this->assertSame('10.0.0.1', $this->service->getIp());
    }

    #[Test]
    #[TestDox('getFixedVerifyCode 返回当前固定验证码')]
    public function get_fixed_verify_code_returns_value(): void
    {
        $this->service->setFixedVerifyCode('999999');

        $this->assertSame('999999', $this->service->getFixedVerifyCode());
    }

    #[Test]
    #[TestDox('未设置 fixedVerifyCode 时 getFixedVerifyCode 返回 null')]
    public function get_fixed_verify_code_returns_null_when_not_set(): void
    {
        $this->assertNull($this->service->getFixedVerifyCode());
    }
}
