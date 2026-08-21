<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\System\PhoneCode;
use App\Services\SmsCaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SmsCaptchaService 单元测试
 */
#[CoversClass(SmsCaptchaService::class)]
#[Group('services')]
#[Group('sms-captcha')]
class SmsCaptchaServiceTest extends TestCase
{
    use RefreshDatabase;

    private SmsCaptchaService $service;

    private string $phone = '13800000001';

    private string $ip = '127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SmsCaptchaService($this->phone, $this->ip);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('sms_captcha:'.$this->phone);
        parent::tearDown();
    }

    #[Test]
    #[TestDox('make 创建实例返回正确类型')]
    public function make_returns_instance(): void
    {
        $service = SmsCaptchaService::make($this->phone, $this->ip, 'login');

        $this->assertInstanceOf(SmsCaptchaService::class, $service);
    }

    #[Test]
    #[TestDox('make 不传 scene 时默认为 default')]
    public function make_defaults_scene_to_default(): void
    {
        $service = SmsCaptchaService::make($this->phone, $this->ip);

        $service->send();
        $this->assertDatabaseHas('phone_codes', [
            'phone' => $this->phone,
            'scene' => 'default',
        ]);
    }

    #[Test]
    #[TestDox('send 首次发送生成验证码并写入数据库')]
    public function send_creates_phone_code_record(): void
    {
        $data = $this->service->send();

        $this->assertDatabaseHas('phone_codes', [
            'phone' => $this->phone,
            'ip' => $this->ip,
        ]);
        $this->assertArrayHasKey('hash', $data);
        $this->assertArrayHasKey('wait_time', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('scene', $data);
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
        PhoneCode::create([
            'phone' => $this->phone,
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
        PhoneCode::create([
            'phone' => $this->phone,
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
        PhoneCode::create([
            'phone' => $this->phone,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => 0,
        ]);

        $this->assertTrue($this->service->validate('123456', true));
        $this->assertDatabaseHas('phone_codes', [
            'phone' => $this->phone,
            'state' => PhoneCode::USED_STATE,
        ]);
    }

    #[Test]
    #[TestDox('validate 错误验证码返回 false 并增加验证次数')]
    public function validate_wrong_code_returns_false_and_increments_count(): void
    {
        PhoneCode::create([
            'phone' => $this->phone,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => 0,
        ]);

        $this->assertFalse($this->service->validate('000000', true));
        $this->assertDatabaseHas('phone_codes', [
            'phone' => $this->phone,
            'verify_count' => 1,
        ]);
    }

    #[Test]
    #[TestDox('validate 超过测试限制时清除限速')]
    public function validate_clears_rate_limiter_when_exceeding_test_limit(): void
    {
        $this->service->setTestLimit(2);
        PhoneCode::create([
            'phone' => $this->phone,
            'code' => '123456',
            'ip' => $this->ip,
            'state' => 0,
            'verify_count' => 3,
        ]);

        $this->assertFalse($this->service->validate('000000', true));

        // 限速器应被清除
        $this->assertFalse(RateLimiter::tooManyAttempts('sms_captcha:'.$this->phone, 1));
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
    #[TestDox('getIpSendCount 返回 IP 发送次数')]
    public function get_ip_send_count_returns_count(): void
    {
        PhoneCode::create(['phone' => '13800000002', 'code' => '111', 'ip' => $this->ip, 'state' => 1, 'usage_at' => now()]);
        PhoneCode::create(['phone' => '13800000003', 'code' => '222', 'ip' => $this->ip, 'state' => 1, 'usage_at' => now()]);

        $this->assertSame(2, $this->service->getIpSendCount());
    }

    #[Test]
    #[TestDox('getPhoneSendCount 返回手机号发送次数')]
    public function get_phone_send_count_returns_count(): void
    {
        PhoneCode::create(['phone' => $this->phone, 'code' => '111', 'ip' => '10.0.0.1', 'state' => 1, 'usage_at' => now()]);
        PhoneCode::create(['phone' => $this->phone, 'code' => '222', 'ip' => '10.0.0.2', 'state' => 1, 'usage_at' => now()]);

        $this->assertSame(2, $this->service->getPhoneSendCount());
    }

    #[Test]
    #[TestDox('getSendCount 返回手机号和 IP 的总发送次数')]
    public function get_send_count_returns_combined_count(): void
    {
        PhoneCode::create(['phone' => $this->phone, 'code' => '111', 'ip' => $this->ip, 'state' => 1, 'usage_at' => now()]);
        PhoneCode::create(['phone' => '13800000002', 'code' => '222', 'ip' => $this->ip, 'state' => 1, 'usage_at' => now()]);
        PhoneCode::create(['phone' => $this->phone, 'code' => '333', 'ip' => '10.0.0.2', 'state' => 1, 'usage_at' => now()]);

        // IP count: 2, Phone count: 2 → total = 4
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
    #[TestDox('setScene 链式调用返回当前实例')]
    public function set_scene_returns_self(): void
    {
        $result = $this->service->setScene('register');

        $this->assertSame($this->service, $result);
    }

    #[Test]
    #[TestDox('setTestLimit 链式调用返回当前实例')]
    public function set_test_limit_returns_self(): void
    {
        $result = $this->service->setTestLimit(5);

        $this->assertSame($this->service, $result);
    }
}
