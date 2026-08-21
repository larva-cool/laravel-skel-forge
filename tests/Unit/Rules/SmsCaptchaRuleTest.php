<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\SmsCaptchaRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 短信验证码检测规则单元测试
 */
#[CoversClass(SmsCaptchaRule::class)]
#[Group('rules')]
class SmsCaptchaRuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试正确的验证码通过验证
     */
    #[Test]
    #[TestDox('正确的短信验证码通过')]
    public function correct_verification_code_passes(): void
    {
        $validator = Validator::make(
            ['phone' => '13800138000', 'code' => '123456'],
            ['code' => new SmsCaptchaRule('phone', '127.0.0.1')]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * 测试错误的验证码失败验证
     */
    #[Test]
    #[TestDox('错误的短信验证码失败')]
    public function incorrect_verification_code_fails(): void
    {
        $validator = Validator::make(
            ['phone' => '13800138000', 'code' => '654321'],
            ['code' => new SmsCaptchaRule('phone', '127.0.0.1')]
        );

        $this->assertFalse($validator->passes());
    }

    /**
     * 测试 clientIp 为 null 时使用默认值
     */
    #[Test]
    #[TestDox('clientIp 为 null 时也能正常验证')]
    public function works_with_null_client_ip(): void
    {
        $validator = Validator::make(
            ['phone' => '13800138000', 'code' => '123456'],
            ['code' => new SmsCaptchaRule('phone', null)]
        );

        $this->assertTrue($validator->passes());
    }
}
