<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\MailCaptchaRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 邮件验证码检测规则单元测试
 */
#[CoversClass(MailCaptchaRule::class)]
#[Group('rules')]
class MailCaptchaRuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试正确的验证码通过验证
     */
    #[Test]
    #[TestDox('正确的邮件验证码通过')]
    public function correct_verification_code_passes(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com', 'code' => '123456'],
            ['code' => new MailCaptchaRule('email', '127.0.0.1')]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * 测试错误的验证码失败验证
     */
    #[Test]
    #[TestDox('错误的邮件验证码失败')]
    public function incorrect_verification_code_fails(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com', 'code' => '654321'],
            ['code' => new MailCaptchaRule('email', '127.0.0.1')]
        );

        $this->assertFalse($validator->passes());
    }
}
