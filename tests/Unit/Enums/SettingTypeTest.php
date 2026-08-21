<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\SettingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SettingType 枚举单元测试
 */
#[CoversClass(SettingType::class)]
#[Group('enums')]
class SettingTypeTest extends TestCase
{
    /**
     * 测试整型常量值
     */
    #[Test]
    #[TestDox('CAST_TYPE_INT 常量值为 int')]
    public function cast_type_int_has_correct_value(): void
    {
        $this->assertSame('int', SettingType::CAST_TYPE_INT);
    }

    /**
     * 测试浮点型常量值
     */
    #[Test]
    #[TestDox('CAST_TYPE_FLOAT 常量值为 float')]
    public function cast_type_float_has_correct_value(): void
    {
        $this->assertSame('float', SettingType::CAST_TYPE_FLOAT);
    }

    /**
     * 测试布尔型常量值
     */
    #[Test]
    #[TestDox('CAST_TYPE_BOOL 常量值为 bool')]
    public function cast_type_bool_has_correct_value(): void
    {
        $this->assertSame('bool', SettingType::CAST_TYPE_BOOL);
    }

    /**
     * 测试字符串型常量值
     */
    #[Test]
    #[TestDox('CAST_TYPE_STRING 常量值为 string')]
    public function cast_type_string_has_correct_value(): void
    {
        $this->assertSame('string', SettingType::CAST_TYPE_STRING);
    }

    /**
     * 测试所有常量值唯一
     */
    #[Test]
    #[TestDox('所有常量值互不重复')]
    public function all_constant_values_are_unique(): void
    {
        $values = [
            SettingType::CAST_TYPE_INT,
            SettingType::CAST_TYPE_FLOAT,
            SettingType::CAST_TYPE_BOOL,
            SettingType::CAST_TYPE_STRING,
        ];

        $this->assertSame($values, array_unique($values));
    }

    /**
     * 测试所有常量均为字符串类型
     */
    #[Test]
    #[TestDox('所有常量均为字符串类型')]
    public function all_constants_are_string_type(): void
    {
        $this->assertIsString(SettingType::CAST_TYPE_INT);
        $this->assertIsString(SettingType::CAST_TYPE_FLOAT);
        $this->assertIsString(SettingType::CAST_TYPE_BOOL);
        $this->assertIsString(SettingType::CAST_TYPE_STRING);
    }
}
