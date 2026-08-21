<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\NameRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 昵称、姓名验证规则单元测试
 */
#[CoversClass(NameRule::class)]
#[Group('rules')]
class NameRuleTest extends TestCase
{
    /**
     * 测试有效的昵称/姓名
     */
    #[Test]
    #[TestDox('验证有效的昵称/姓名通过')]
    #[DataProvider('validNamesProvider')]
    public function valid_names_pass_validation(string $name): void
    {
        $validator = Validator::make(
            ['name' => $name],
            ['name' => new NameRule]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * 测试无效的昵称/姓名
     */
    #[Test]
    #[TestDox('验证无效的昵称/姓名失败')]
    #[DataProvider('invalidNamesProvider')]
    public function invalid_names_fail_validation(string $name): void
    {
        $validator = Validator::make(
            ['name' => $name],
            ['name' => new NameRule]
        );

        $this->assertFalse($validator->passes());
    }

    /**
     * 有效昵称/姓名数据提供器
     */
    public static function validNamesProvider(): array
    {
        return [
            '纯字母' => ['username'],
            '纯数字' => ['123456'],
            '中文字符' => ['张三'],
            '字母数字混合' => ['user123'],
            '包含下划线' => ['user_name'],
            '包含连字符' => ['user-name'],
            '包含点' => ['user.name'],
            '包含@' => ['user@name'],
            '中文和英文混合' => ['张三name'],
            '混合多种字符' => ['张-三.123@name'],
        ];
    }

    /**
     * 无效昵称/姓名数据提供器
     */
    public static function invalidNamesProvider(): array
    {
        return [
            '包含空格' => ['user name'],
            '包含特殊字符' => ['user#name'],
            '包含感叹号' => ['user!'],
        ];
    }
}
