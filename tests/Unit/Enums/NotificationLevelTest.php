<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NotificationLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * NotificationLevel 枚举单元测试
 */
#[CoversClass(NotificationLevel::class)]
#[Group('enums')]
class NotificationLevelTest extends TestCase
{
    #[Test]
    #[TestDox('所有枚举 case 的 value 与设计一致')]
    public function enum_values(): void
    {
        $this->assertSame('info', NotificationLevel::INFO->value);
        $this->assertSame('success', NotificationLevel::SUCCESS->value);
        $this->assertSame('warning', NotificationLevel::WARNING->value);
        $this->assertSame('error', NotificationLevel::ERROR->value);
    }

    #[Test]
    #[TestDox('label 返回正确中文标签')]
    public function label_returns_chinese(): void
    {
        $this->assertSame('提示', NotificationLevel::INFO->label());
        $this->assertSame('成功', NotificationLevel::SUCCESS->label());
        $this->assertSame('警告', NotificationLevel::WARNING->label());
        $this->assertSame('错误', NotificationLevel::ERROR->label());
    }

    #[Test]
    #[TestDox('values 返回全部枚举值')]
    public function values_returns_all_values(): void
    {
        $this->assertSame(
            ['info', 'success', 'warning', 'error'],
            NotificationLevel::values(),
        );
    }

    #[Test]
    #[TestDox('tryFrom 支持任意已知字符串')]
    public function try_from_known_value(): void
    {
        $this->assertSame(NotificationLevel::ERROR, NotificationLevel::tryFrom('error'));
        $this->assertNull(NotificationLevel::tryFrom('unknown'));
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 与 label')]
    public function json_serialize_shape(): void
    {
        $this->assertSame(
            ['value' => 'error', 'label' => '错误'],
            NotificationLevel::ERROR->jsonSerialize(),
        );
    }
}
