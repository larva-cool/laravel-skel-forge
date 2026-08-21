<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\System\MailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * MailCode 模型单元测试
 */
#[CoversClass(MailCode::class)]
#[Group('models')]
class MailCodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试工厂创建验证码
     */
    #[Test]
    #[TestDox('工厂创建验证码并验证默认值')]
    public function factory_creates_mail_code_with_defaults(): void
    {
        $mailCode = MailCode::factory()->create();

        $this->assertInstanceOf(MailCode::class, $mailCode);
        $this->assertSame(0, $mailCode->state);
        $this->assertSame(0, $mailCode->verify_count);
    }

    /**
     * 测试 CREATED_AT 映射为 send_at
     */
    #[Test]
    #[TestDox('CREATED_AT 常量映射为 send_at')]
    public function created_at_is_mapped_to_send_at(): void
    {
        $this->assertSame('send_at', MailCode::CREATED_AT);
    }

    /**
     * 测试 UPDATED_AT 为 null
     */
    #[Test]
    #[TestDox('UPDATED_AT 常量为 null')]
    public function updated_at_is_null(): void
    {
        $this->assertNull(MailCode::UPDATED_AT);
    }

    /**
     * 测试 USED_STATE 常量
     */
    #[Test]
    #[TestDox('USED_STATE 常量值为 1')]
    public function used_state_constant_is_one(): void
    {
        $this->assertSame(1, MailCode::USED_STATE);
    }

    /**
     * 测试 build 方法创建验证码
     */
    #[Test]
    #[TestDox('build 方法创建验证码记录')]
    public function build_creates_mail_code(): void
    {
        $mailCode = MailCode::build('test@example.com', '127.0.0.1', '123456');

        $this->assertInstanceOf(MailCode::class, $mailCode);
        $this->assertSame('test@example.com', $mailCode->email);
        $this->assertSame('127.0.0.1', $mailCode->ip);
        $this->assertSame('123456', $mailCode->code);
        $this->assertSame(0, $mailCode->state);
    }

    /**
     * 测试 makeUsed 标记为已使用
     */
    #[Test]
    #[TestDox('makeUsed 标记验证码为已使用')]
    public function make_used_marks_code_as_used(): void
    {
        $mailCode = MailCode::factory()->create();
        $this->assertTrue($mailCode->makeUsed());

        $mailCode->refresh();
        $this->assertSame(MailCode::USED_STATE, $mailCode->state);
        $this->assertNotNull($mailCode->usage_at);
    }

    /**
     * 测试 validate 验证码正确
     */
    #[Test]
    #[TestDox('validate 验证码正确返回 true 并标记为已使用')]
    public function validate_returns_true_with_correct_code(): void
    {
        $mailCode = MailCode::factory()->create(['code' => '123456']);

        $result = $mailCode->validate('123456', true);

        $this->assertTrue($result);
        $mailCode->refresh();
        $this->assertSame(MailCode::USED_STATE, $mailCode->state);
        $this->assertNotNull($mailCode->usage_at);
    }

    /**
     * 测试 validate 验证码不区分大小写
     */
    #[Test]
    #[TestDox('validate 不区分大小写验证')]
    public function validate_case_insensitive(): void
    {
        $mailCode = MailCode::factory()->create(['code' => 'ABC123']);

        $result = $mailCode->validate('abc123', false);

        $this->assertTrue($result);
    }

    /**
     * 测试 validate 验证码区分大小写
     */
    #[Test]
    #[TestDox('validate 区分大小写验证')]
    public function validate_case_sensitive(): void
    {
        $mailCode = MailCode::factory()->create(['code' => 'ABC123']);

        $result = $mailCode->validate('abc123', true);

        $this->assertFalse($result);
    }

    /**
     * 测试 validate 验证码错误增加验证次数
     */
    #[Test]
    #[TestDox('validate 验证码错误时增加验证次数')]
    public function validate_increments_verify_count_on_wrong_code(): void
    {
        $mailCode = MailCode::factory()->create(['code' => '123456', 'verify_count' => 0]);

        $result = $mailCode->validate('654321', true);

        $this->assertFalse($result);
        $mailCode->refresh();
        $this->assertSame(1, $mailCode->verify_count);
    }

    /**
     * 测试 validate 已使用的验证码返回 false
     */
    #[Test]
    #[TestDox('validate 已使用的验证码返回 false')]
    public function validate_returns_false_for_used_code(): void
    {
        $mailCode = MailCode::factory()->used()->create(['code' => '123456']);

        $result = $mailCode->validate('123456', true);

        $this->assertFalse($result);
    }

    /**
     * 测试 getCode 获取指定邮箱未使用的验证码
     */
    #[Test]
    #[TestDox('getCode 获取指定邮箱最新未使用的验证码')]
    public function get_code_returns_latest_unused_code(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        MailCode::factory()->create([
            'email' => 'test@example.com',
            'code' => '111111',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        MailCode::factory()->create([
            'email' => 'test@example.com',
            'code' => '222222',
            'state' => 0,
            'send_at' => Carbon::create(2025, 1, 1, 11, 0, 0),
        ]);
        MailCode::factory()->create([
            'email' => 'test@example.com',
            'code' => '333333',
            'state' => MailCode::USED_STATE,
            'send_at' => Carbon::create(2025, 1, 1, 12, 0, 0),
        ]);

        $code = MailCode::getCode('test@example.com');

        $this->assertNotNull($code);
        $this->assertSame('222222', $code->code);
        $this->assertSame(0, $code->state);

        Carbon::setTestNow();
    }

    /**
     * 测试 getCode 无匹配返回 null
     */
    #[Test]
    #[TestDox('getCode 无匹配邮箱返回 null')]
    public function get_code_returns_null_when_not_found(): void
    {
        $result = MailCode::getCode('nonexistent@example.com');

        $this->assertNull($result);
    }

    /**
     * 测试 getIpTodayCount 获取今日 IP 发送次数
     */
    #[Test]
    #[TestDox('getIpTodayCount 统计今日 IP 发送次数')]
    public function get_ip_today_count_returns_today_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        MailCode::factory()->count(3)->create([
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        MailCode::factory()->create([
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2024, 12, 31, 10, 0, 0),
        ]);
        MailCode::factory()->create([
            'ip' => '192.168.1.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);

        $this->assertSame(3, MailCode::getIpTodayCount('127.0.0.1'));
        $this->assertSame(1, MailCode::getIpTodayCount('192.168.1.1'));
        $this->assertSame(0, MailCode::getIpTodayCount('10.0.0.1'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getMailTodayCount 获取邮箱今日发送次数
     */
    #[Test]
    #[TestDox('getMailTodayCount 统计今日邮箱发送次数')]
    public function get_mail_today_count_returns_today_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        MailCode::factory()->count(2)->create([
            'email' => 'test@example.com',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        MailCode::factory()->create([
            'email' => 'test@example.com',
            'send_at' => Carbon::create(2024, 12, 31, 10, 0, 0),
        ]);
        MailCode::factory()->create([
            'email' => 'other@example.com',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);

        $this->assertSame(2, MailCode::getMailTodayCount('test@example.com'));
        $this->assertSame(1, MailCode::getMailTodayCount('other@example.com'));
        $this->assertSame(0, MailCode::getMailTodayCount('none@example.com'));

        Carbon::setTestNow();
    }

    /**
     * 测试 getTodayCount 获取今日总发送次数
     */
    #[Test]
    #[TestDox('getTodayCount 统计邮箱和 IP 今日总发送次数')]
    public function get_today_count_returns_combined_count(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        MailCode::factory()->count(2)->create([
            'email' => 'test@example.com',
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);
        MailCode::factory()->create([
            'email' => 'other@example.com',
            'ip' => '127.0.0.1',
            'send_at' => Carbon::create(2025, 1, 1, 10, 0, 0),
        ]);

        // getTodayCount = getIpTodayCount + getMailTodayCount
        // IP 127.0.0.1 有 3 条，邮箱 test@example.com 有 2 条
        $this->assertSame(5, MailCode::getTodayCount('test@example.com', '127.0.0.1'));
        // IP 192.168.1.1 有 0 条，邮箱 test@example.com 有 2 条
        $this->assertSame(2, MailCode::getTodayCount('test@example.com', '192.168.1.1'));
        // IP 192.168.1.1 有 0 条，邮箱 other@example.com 有 1 条
        $this->assertSame(1, MailCode::getTodayCount('other@example.com', '192.168.1.1'));

        Carbon::setTestNow();
    }
}
