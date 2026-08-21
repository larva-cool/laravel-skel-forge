<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\System\PhoneCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * PhoneCode 模型单元测试
 */
#[CoversClass(PhoneCode::class)]
#[Group('models')]
class PhoneCodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试工厂创建验证码
     */
    #[Test]
    #[TestDox('工厂创建验证码并验证默认值')]
    public function factory_creates_phone_code_with_defaults(): void
    {
        $phoneCode = PhoneCode::factory()->create();

        $this->assertInstanceOf(PhoneCode::class, $phoneCode);
        $this->assertSame(0, $phoneCode->state);
        $this->assertSame(0, $phoneCode->verify_count);
        $this->assertSame('default', $phoneCode->scene);
    }

    /**
     * 测试 CREATED_AT 映射为 send_at
     */
    #[Test]
    #[TestDox('CREATED_AT 常量映射为 send_at')]
    public function created_at_is_mapped_to_send_at(): void
    {
        $this->assertSame('send_at', PhoneCode::CREATED_AT);
    }

    /**
     * 测试 UPDATED_AT 为 null
     */
    #[Test]
    #[TestDox('UPDATED_AT 常量为 null')]
    public function updated_at_is_null(): void
    {
        $this->assertNull(PhoneCode::UPDATED_AT);
    }

    /**
     * 测试 USED_STATE 常量
     */
    #[Test]
    #[TestDox('USED_STATE 常量值为 1')]
    public function used_state_constant_is_one(): void
    {
        $this->assertSame(1, PhoneCode::USED_STATE);
    }

    /**
     * 测试 build 方法创建验证码
     */
    #[Test]
    #[TestDox('build 方法创建验证码记录')]
    public function build_creates_phone_code(): void
    {
        $phoneCode = PhoneCode::build('13800138000', '127.0.0.1', '123456');

        $this->assertInstanceOf(PhoneCode::class, $phoneCode);
        $this->assertSame('13800138000', $phoneCode->phone);
        $this->assertSame('127.0.0.1', $phoneCode->ip);
        $this->assertSame('123456', $phoneCode->code);
        $this->assertSame('default', $phoneCode->scene);
    }

    /**
     * 测试 build 方法带场景参数
     */
    #[Test]
    #[TestDox('build 方法带场景参数创建验证码')]
    public function build_creates_phone_code_with_scene(): void
    {
        $phoneCode = PhoneCode::build('13800138000', '127.0.0.1', '123456', 'login');

        $this->assertSame('login', $phoneCode->scene);
    }

    /**
     * 测试 makeUsed 标记为已使用
     */
    #[Test]
    #[TestDox('makeUsed 标记验证码为已使用')]
    public function make_used_marks_code_as_used(): void
    {
        $phoneCode = PhoneCode::factory()->create();
        $this->assertTrue($phoneCode->makeUsed());

        $phoneCode->refresh();
        $this->assertSame(PhoneCode::USED_STATE, $phoneCode->state);
        $this->assertNotNull($phoneCode->usage_at);
    }

    /**
     * 测试 validate 验证码正确
     */
    #[Test]
    #[TestDox('validate 验证码正确返回 true 并标记为已使用')]
    public function validate_returns_true_with_correct_code(): void
    {
        $phoneCode = PhoneCode::factory()->create(['code' => '123456']);

        $result = $phoneCode->validate('123456', true);

        $this->assertTrue($result);
        $phoneCode->refresh();
        $this->assertSame(PhoneCode::USED_STATE, $phoneCode->state);
        $this->assertNotNull($phoneCode->usage_at);
    }

    /**
     * 测试 validate 验证码不区分大小写
     */
    #[Test]
    #[TestDox('validate 不区分大小写验证')]
    public function validate_case_insensitive(): void
    {
        $phoneCode = PhoneCode::factory()->create(['code' => 'ABC123']);

        $result = $phoneCode->validate('abc123', false);

        $this->assertTrue($result);
    }

    /**
     * 测试 validate 验证码区分大小写
     */
    #[Test]
    #[TestDox('validate 区分大小写验证')]
    public function validate_case_sensitive(): void
    {
        $phoneCode = PhoneCode::factory()->create(['code' => 'ABC123']);

        $result = $phoneCode->validate('abc123', true);

        $this->assertFalse($result);
    }

    /**
     * 测试 validate 验证码错误增加验证次数
     */
    #[Test]
    #[TestDox('validate 验证码错误时增加验证次数')]
    public function validate_increments_verify_count_on_wrong_code(): void
    {
        $phoneCode = PhoneCode::factory()->create(['code' => '123456', 'verify_count' => 0]);

        $result = $phoneCode->validate('654321', true);

        $this->assertFalse($result);
        $phoneCode->refresh();
        $this->assertSame(1, $phoneCode->verify_count);
    }

    /**
     * 测试 getCode 获取指定手机号未使用的验证码
     */
    #[Test]
    #[TestDox('getCode 获取指定手机号最新未使用的验证码')]
    public function get_code_returns_latest_unused_code(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        PhoneCode::factory()->create([
            'phone' => '13800138000',
            'code' => '111111',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        PhoneCode::factory()->create([
            'phone' => '13800138000',
            'code' => '222222',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 11, 0, 0),
        ]);
        PhoneCode::factory()->used()->create([
            'phone' => '13800138000',
            'code' => '333333',
            'send_at' => Carbon::create(2025, 1, 1, 12, 0, 0),
        ]);

        $code = PhoneCode::getCode('13800138000');

        $this->assertNotNull($code);
        $this->assertSame('222222', $code->code);
        $this->assertSame(0, $code->state);

        Carbon::setTestNow();
    }

    /**
     * 测试 getCode 无匹配返回 null
     */
    #[Test]
    #[TestDox('getCode 无匹配手机号返回 null')]
    public function get_code_returns_null_when_not_found(): void
    {
        $this->assertNull(PhoneCode::getCode('19999999999'));
    }

    /**
     * 测试 getIpTodayCount 获取今日 IP 发送次数
     */
    #[Test]
    #[TestDox('getIpTodayCount 统计今日 IP 已使用的发送次数')]
    public function get_ip_today_count_returns_today_used_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        // 今日已使用 - 计数
        PhoneCode::factory()->used()->create([
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        // 今日未使用 - 不计数
        PhoneCode::factory()->create([
            'ip' => '127.0.0.1',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 11, 0, 0),
        ]);
        // 昨日已使用 - 不计数
        PhoneCode::factory()->used()->create([
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2024, 12, 31, 10, 0, 0),
        ]);

        $this->assertSame(1, PhoneCode::getIpTodayCount('127.0.0.1'));
        $this->assertSame(0, PhoneCode::getIpTodayCount('192.168.1.1'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getPhoneTodayCount 获取手机号今日发送次数
     */
    #[Test]
    #[TestDox('getPhoneTodayCount 统计今日手机号已使用的发送次数')]
    public function get_phone_today_count_returns_today_used_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        PhoneCode::factory()->used()->count(2)->create([
            'phone' => '13800138000',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        PhoneCode::factory()->create([
            'phone' => '13800138000',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 11, 0, 0),
        ]);
        PhoneCode::factory()->used()->create([
            'phone' => '13900139000',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);

        $this->assertSame(2, PhoneCode::getPhoneTodayCount('13800138000'));
        $this->assertSame(1, PhoneCode::getPhoneTodayCount('13900139000'));
        $this->assertSame(0, PhoneCode::getPhoneTodayCount('19999999999'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getTodayCount 获取今日总发送次数
     */
    #[Test]
    #[TestDox('getTodayCount 统计手机号和 IP 今日已使用的总发送次数')]
    public function get_today_count_returns_combined_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        PhoneCode::factory()->used()->count(2)->create([
            'phone' => '13800138000',
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        PhoneCode::factory()->used()->create([
            'phone' => '13900139000',
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);

        // getTodayCount = getIpTodayCount + getPhoneTodayCount
        // IP 127.0.0.1 有 3 条已使用，手机号 13800138000 有 2 条已使用
        $this->assertSame(5, PhoneCode::getTodayCount('13800138000', '127.0.0.1'));
        // IP 192.168.1.1 有 0 条，手机号 13800138000 有 2 条
        $this->assertSame(2, PhoneCode::getTodayCount('13800138000', '192.168.1.1'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getIpHourCount 获取当前小时 IP 发送次数
     */
    #[Test]
    #[TestDox('getIpHourCount 统计当前小时 IP 已使用的发送次数')]
    public function get_ip_hour_count_returns_current_hour_used_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 10, 30, 0));

        // 当前小时已使用
        PhoneCode::factory()->used()->create([
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 15, 0),
        ]);
        // 当前小时未使用 - 不计数
        PhoneCode::factory()->create([
            'ip' => '127.0.0.1',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 10, 20, 0),
        ]);
        // 上一小时 - 不计数
        PhoneCode::factory()->used()->create([
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 9, 30, 0),
        ]);

        $this->assertSame(1, PhoneCode::getIpHourCount('127.0.0.1'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getPhoneHourCount 获取当前小时手机号发送次数
     */
    #[Test]
    #[TestDox('getPhoneHourCount 统计当前小时手机号已使用的发送次数')]
    public function get_phone_hour_count_returns_current_hour_used_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 10, 30, 0));

        PhoneCode::factory()->used()->count(2)->create([
            'phone' => '13800138000',
            'send_at' => Carbon::create(2025, 1, 1, 10, 15, 0),
        ]);
        PhoneCode::factory()->create([
            'phone' => '13800138000',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 10, 20, 0),
        ]);
        PhoneCode::factory()->used()->create([
            'phone' => '13800138000',
            'send_at' => Carbon::create(2025, 1, 1, 9, 30, 0),
        ]);

        $this->assertSame(2, PhoneCode::getPhoneHourCount('13800138000'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getHourCount 获取当前小时总发送次数
     */
    #[Test]
    #[TestDox('getHourCount 统计手机号和 IP 当前小时已使用的总发送次数')]
    public function get_hour_count_returns_combined_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 10, 30, 0));

        PhoneCode::factory()->used()->create([
            'phone' => '13800138000',
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 15, 0),
        ]);
        PhoneCode::factory()->used()->create([
            'phone' => '13900139000',
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 20, 0),
        ]);

        // getHourCount = getIpHourCount + getPhoneHourCount
        // IP 127.0.0.1 有 2 条，手机号 13800138000 有 1 条
        $this->assertSame(3, PhoneCode::getHourCount('13800138000', '127.0.0.1'));

        Carbon::setTestNow();
    }

    /**
     * 测试 result 字段 JSON 转换
     */
    #[Test]
    #[TestDox('result 字段自动转换为数组')]
    public function result_is_cast_to_array(): void
    {
        $phoneCode = PhoneCode::factory()->create([
            'result' => ['status' => 'success', 'message_id' => '12345'],
        ]);

        $this->assertIsArray($phoneCode->result);
        $this->assertSame('success', $phoneCode->result['status']);
        $this->assertSame('12345', $phoneCode->result['message_id']);
    }

    /**
     * 测试 result 字段为 null 时返回 null
     */
    #[Test]
    #[TestDox('result 字段为 null 时返回 null')]
    public function result_returns_null_when_not_set(): void
    {
        $phoneCode = PhoneCode::factory()->create(['result' => null]);

        $this->assertNull($phoneCode->result);
    }
}
