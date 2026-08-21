<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\SmsCaptchaSendCheckRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 检测手机号是否有权发送验证码规则单元测试
 */
#[CoversClass(SmsCaptchaSendCheckRule::class)]
#[Group('rules')]
class SmsCaptchaSendCheckRuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试在测试环境中验证总是通过
     */
    #[Test]
    #[TestDox('测试环境中验证总是通过')]
    public function validation_always_passes_in_testing_environment(): void
    {
        $validator = Validator::make(
            ['phone' => '13800138000'],
            ['phone' => new SmsCaptchaSendCheckRule('127.0.0.1')]
        );

        $this->assertTrue($validator->passes());
    }
}
