<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AdminStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * AdminStatus 枚举单元测试
 */
#[CoversClass(AdminStatus::class)]
#[Group('enums')]
class AdminStatusTest extends TestCase
{
    #[Test]
    #[TestDox('DISABLED 值为 0')]
    public function disabled_has_correct_value(): void
    {
        $this->assertSame(0, AdminStatus::DISABLED->value);
    }

    #[Test]
    #[TestDox('ACTIVE 值为 1')]
    public function active_has_correct_value(): void
    {
        $this->assertSame(1, AdminStatus::ACTIVE->value);
    }

    #[Test]
    #[TestDox('label 返回正确标签')]
    public function label_returns_correct_string(): void
    {
        $this->assertSame('禁用', AdminStatus::DISABLED->label());
        $this->assertSame('正常', AdminStatus::ACTIVE->label());
    }

    #[Test]
    #[TestDox('isActive 正确判断')]
    public function is_active(): void
    {
        $this->assertTrue(AdminStatus::ACTIVE->isActive());
        $this->assertFalse(AdminStatus::DISABLED->isActive());
    }

    #[Test]
    #[TestDox('isDisabled 正确判断')]
    public function is_disabled(): void
    {
        $this->assertTrue(AdminStatus::DISABLED->isDisabled());
        $this->assertFalse(AdminStatus::ACTIVE->isDisabled());
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 和 label')]
    public function json_serialize_returns_value_and_label(): void
    {
        $json = AdminStatus::ACTIVE->jsonSerialize();

        $this->assertSame(['value' => 1, 'label' => '正常'], $json);
    }

    #[Test]
    #[TestDox('keys 返回所有枚举键名')]
    public function keys_returns_all_names(): void
    {
        $this->assertSame(['DISABLED', 'ACTIVE'], AdminStatus::keys());
    }

    #[Test]
    #[TestDox('values 返回所有枚举值')]
    public function values_returns_all_values(): void
    {
        $this->assertSame([0, 1], AdminStatus::values());
    }

    #[Test]
    #[TestDox('options 返回键值对')]
    public function options_returns_key_value_pairs(): void
    {
        $this->assertSame([0 => '禁用', 1 => '正常'], AdminStatus::options());
    }
}
