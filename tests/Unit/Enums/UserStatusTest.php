<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\UserStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * UserStatus 枚举单元测试
 */
#[CoversClass(UserStatus::class)]
#[Group('enums')]
class UserStatusTest extends TestCase
{
    #[Test]
    #[TestDox('FROZEN 值为 0')]
    public function frozen_has_correct_value(): void
    {
        $this->assertSame(0, UserStatus::FROZEN->value);
    }

    #[Test]
    #[TestDox('ACTIVE 值为 1')]
    public function active_has_correct_value(): void
    {
        $this->assertSame(1, UserStatus::ACTIVE->value);
    }

    #[Test]
    #[TestDox('label 返回正确标签')]
    public function label_returns_correct_string(): void
    {
        $this->assertSame('已冻结', UserStatus::FROZEN->label());
        $this->assertSame('正常', UserStatus::ACTIVE->label());
    }

    #[Test]
    #[TestDox('isActive 正确判断')]
    public function is_active(): void
    {
        $this->assertTrue(UserStatus::ACTIVE->isActive());
        $this->assertFalse(UserStatus::FROZEN->isActive());
    }

    #[Test]
    #[TestDox('isFrozen 正确判断')]
    public function is_frozen(): void
    {
        $this->assertTrue(UserStatus::FROZEN->isFrozen());
        $this->assertFalse(UserStatus::ACTIVE->isFrozen());
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 和 label')]
    public function json_serialize_returns_value_and_label(): void
    {
        $json = UserStatus::FROZEN->jsonSerialize();

        $this->assertSame(['value' => 0, 'label' => '已冻结'], $json);
    }

    #[Test]
    #[TestDox('keys 返回所有枚举键名')]
    public function keys_returns_all_names(): void
    {
        $this->assertSame(['FROZEN', 'ACTIVE'], UserStatus::keys());
    }

    #[Test]
    #[TestDox('values 返回所有枚举值')]
    public function values_returns_all_values(): void
    {
        $this->assertSame([0, 1], UserStatus::values());
    }

    #[Test]
    #[TestDox('options 返回键值对')]
    public function options_returns_key_value_pairs(): void
    {
        $this->assertSame([0 => '已冻结', 1 => '正常'], UserStatus::options());
    }
}
