<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\UsernameRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户名验证规则单元测试
 */
#[CoversClass(UsernameRule::class)]
#[Group('rules')]
class UsernameRuleTest extends TestCase
{
    /**
     * 测试有效的用户名
     */
    #[Test]
    #[TestDox('验证有效的用户名通过')]
    #[DataProvider('validUsernamesProvider')]
    public function valid_usernames_pass_validation(string $username): void
    {
        $validator = Validator::make(
            ['username' => $username],
            ['username' => new UsernameRule]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * 测试无效的用户名
     */
    #[Test]
    #[TestDox('验证无效的用户名失败')]
    #[DataProvider('invalidUsernamesProvider')]
    public function invalid_usernames_fail_validation(mixed $username): void
    {
        $validator = Validator::make(
            ['username' => $username],
            ['username' => new UsernameRule]
        );

        $this->assertFalse($validator->passes());
    }

    /**
     * 有效用户名数据提供器
     */
    public static function validUsernamesProvider(): array
    {
        return [
            '纯字母' => ['username'],
            '纯数字' => ['123456'],
            '字母数字混合' => ['user123'],
            '包含下划线' => ['user_name'],
            '包含连字符' => ['user-name'],
            '混合多种字符' => ['user-123_name'],
        ];
    }

    /**
     * 无效用户名数据提供器
     */
    public static function invalidUsernamesProvider(): array
    {
        return [
            '包含空格' => ['user name'],
            '包含特殊字符' => ['user@name'],
            '包含中文字符' => ['用户名'],
        ];
    }
}
