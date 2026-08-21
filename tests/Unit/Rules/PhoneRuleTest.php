<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\PhoneRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 手机号码验证规则单元测试
 */
#[CoversClass(PhoneRule::class)]
#[Group('rules')]
class PhoneRuleTest extends TestCase
{
    /**
     * 测试有效的手机号
     */
    #[Test]
    #[TestDox('验证有效的手机号通过')]
    #[DataProvider('validPhonesProvider')]
    public function valid_phones_pass_validation(string $phone): void
    {
        $validator = Validator::make(
            ['phone' => $phone],
            ['phone' => new PhoneRule]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * 测试无效的手机号
     */
    #[Test]
    #[TestDox('验证无效的手机号失败')]
    #[DataProvider('invalidPhonesProvider')]
    public function invalid_phones_fail_validation(string $phone): void
    {
        $validator = Validator::make(
            ['phone' => $phone],
            ['phone' => new PhoneRule]
        );

        $this->assertFalse($validator->passes());
    }

    /**
     * 有效手机号数据提供器
     */
    public static function validPhonesProvider(): array
    {
        return [
            '移动138' => ['13800138000'],
            '联通130' => ['13000130000'],
            '电信133' => ['13300133000'],
            '159号段' => ['15900159000'],
            '186号段' => ['18600186000'],
            '176号段' => ['17600176000'],
            '199号段' => ['19900199000'],
        ];
    }

    /**
     * 无效手机号数据提供器
     */
    public static function invalidPhonesProvider(): array
    {
        return [
            '以10开头' => ['10800138000'],
            '以11开头' => ['11800138000'],
            '位数不足11位' => ['1380013800'],
            '位数超过11位' => ['138001380000'],
            '包含字母' => ['138abc13800'],
            '包含特殊字符' => ['138-001-3800'],
            '非1开头' => ['23800138000'],
        ];
    }
}
