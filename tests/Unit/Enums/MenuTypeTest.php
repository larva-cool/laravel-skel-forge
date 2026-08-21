<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\MenuType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * MenuType 枚举单元测试
 */
#[CoversClass(MenuType::class)]
#[Group('enums')]
class MenuTypeTest extends TestCase
{
    #[Test]
    #[TestDox('枚举值正确')]
    public function enum_values_are_correct(): void
    {
        $this->assertSame(0, MenuType::DIRECTORY->value);
        $this->assertSame(1, MenuType::MENU->value);
        $this->assertSame(2, MenuType::BUTTON->value);
        $this->assertSame(3, MenuType::IFRAME->value);
        $this->assertSame(4, MenuType::LINK->value);
    }

    #[Test]
    #[TestDox('label 返回正确标签')]
    public function label_returns_correct_string(): void
    {
        $this->assertSame('目录', MenuType::DIRECTORY->label());
        $this->assertSame('菜单', MenuType::MENU->label());
        $this->assertSame('按钮', MenuType::BUTTON->label());
        $this->assertSame('内嵌', MenuType::IFRAME->label());
        $this->assertSame('外链', MenuType::LINK->label());
    }

    #[Test]
    #[TestDox('isLeaf 目录返回 false，其余返回 true')]
    public function is_leaf(): void
    {
        $this->assertFalse(MenuType::DIRECTORY->isLeaf());
        $this->assertTrue(MenuType::MENU->isLeaf());
        $this->assertTrue(MenuType::BUTTON->isLeaf());
        $this->assertTrue(MenuType::IFRAME->isLeaf());
        $this->assertTrue(MenuType::LINK->isLeaf());
    }

    #[Test]
    #[TestDox('isNavigable 按钮返回 false，其余返回 true')]
    public function is_navigable(): void
    {
        $this->assertTrue(MenuType::DIRECTORY->isNavigable());
        $this->assertTrue(MenuType::MENU->isNavigable());
        $this->assertFalse(MenuType::BUTTON->isNavigable());
        $this->assertTrue(MenuType::IFRAME->isNavigable());
        $this->assertTrue(MenuType::LINK->isNavigable());
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 和 label')]
    public function json_serialize_returns_value_and_label(): void
    {
        $json = MenuType::MENU->jsonSerialize();

        $this->assertSame(['value' => 1, 'label' => '菜单'], $json);
    }

    #[Test]
    #[TestDox('keys 返回所有枚举键名')]
    public function keys_returns_all_names(): void
    {
        $this->assertSame(['DIRECTORY', 'MENU', 'BUTTON', 'IFRAME', 'LINK'], MenuType::keys());
    }

    #[Test]
    #[TestDox('values 返回所有枚举值')]
    public function values_returns_all_values(): void
    {
        $this->assertSame([0, 1, 2, 3, 4], MenuType::values());
    }

    #[Test]
    #[TestDox('options 返回键值对')]
    public function options_returns_key_value_pairs(): void
    {
        $expected = [0 => '目录', 1 => '菜单', 2 => '按钮', 3 => '内嵌', 4 => '外链'];
        $this->assertSame($expected, MenuType::options());
    }
}
