<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Admin;

use App\Enums\MenuType;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * AdminMenu 模型单元测试
 */
#[CoversClass(AdminMenu::class)]
#[Group('models')]
#[Group('admin-menu')]
class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    private function createMenu(array $overrides = []): AdminMenu
    {
        return AdminMenu::create(array_merge([
            'title' => '测试菜单',
            'type' => MenuType::MENU,
            'sort' => 0,
            'is_enable' => true,
            'is_hide' => false,
            'is_hide_tab' => false,
            'is_iframe' => false,
            'keep_alive' => false,
            'is_full_page' => false,
            'fixed_tab' => false,
            'show_badge' => false,
        ], $overrides));
    }

    #[Test]
    #[TestDox('parent 关系返回父级菜单')]
    public function parent_returns_parent_menu(): void
    {
        $parent = $this->createMenu(['title' => '父级']);
        $child = $this->createMenu(['title' => '子级', 'parent_id' => $parent->id]);

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertSame('父级', $child->parent->title);
    }

    #[Test]
    #[TestDox('children 关系返回子菜单集合并按 sort 排序')]
    public function children_returns_sorted_children(): void
    {
        $parent = $this->createMenu(['title' => '父级']);
        $this->createMenu(['title' => '子级B', 'parent_id' => $parent->id, 'sort' => 2]);
        $this->createMenu(['title' => '子级A', 'parent_id' => $parent->id, 'sort' => 1]);

        $parent->refresh();
        $children = $parent->children;

        $this->assertCount(2, $children);
        $this->assertSame('子级A', $children->first()->title);
    }

    #[Test]
    #[TestDox('isRoot 顶级菜单返回 true')]
    public function is_root_returns_true_for_top_level(): void
    {
        $menu = $this->createMenu(['title' => '顶级']);

        $this->assertTrue($menu->isRoot());
    }

    #[Test]
    #[TestDox('isRoot 子级菜单返回 false')]
    public function is_root_returns_false_for_child(): void
    {
        $parent = $this->createMenu(['title' => '父级']);
        $child = $this->createMenu(['title' => '子级', 'parent_id' => $parent->id]);

        $this->assertFalse($child->isRoot());
    }

    #[Test]
    #[TestDox('isDirectory 目录类型返回 true')]
    public function is_directory_returns_true(): void
    {
        $menu = $this->createMenu(['title' => '目录', 'type' => MenuType::DIRECTORY]);

        $this->assertTrue($menu->isDirectory());
    }

    #[Test]
    #[TestDox('isDirectory 非目录类型返回 false')]
    public function is_directory_returns_false(): void
    {
        $menu = $this->createMenu(['title' => '菜单', 'type' => MenuType::MENU]);

        $this->assertFalse($menu->isDirectory());
    }

    #[Test]
    #[TestDox('isButton 按钮类型返回 true')]
    public function is_button_returns_true(): void
    {
        $menu = $this->createMenu(['title' => '按钮', 'type' => MenuType::BUTTON]);

        $this->assertTrue($menu->isButton());
    }

    #[Test]
    #[TestDox('isButton 非按钮类型返回 false')]
    public function is_button_returns_false(): void
    {
        $menu = $this->createMenu(['title' => '菜单', 'type' => MenuType::MENU]);

        $this->assertFalse($menu->isButton());
    }

    #[Test]
    #[TestDox('scopeEnabled 只返回启用的菜单')]
    public function scope_enabled_returns_only_enabled(): void
    {
        $baseCount = AdminMenu::query()->where('is_enable', true)->count();
        $this->createMenu(['title' => '启用A', 'is_enable' => true]);
        $this->createMenu(['title' => '禁用B', 'is_enable' => false]);

        $enabled = AdminMenu::enabled()->get();

        $this->assertCount($baseCount + 1, $enabled);
    }

    #[Test]
    #[TestDox('scopeOrdered 按 sort 排序')]
    public function scope_ordered_sorts_by_sort(): void
    {
        $this->createMenu(['title' => 'SortC', 'sort' => 999]);
        $this->createMenu(['title' => 'SortA', 'sort' => 1]);
        $this->createMenu(['title' => 'SortB', 'sort' => 2]);

        $ordered = AdminMenu::ordered()->where('title', 'like', 'Sort%')->get();

        $this->assertSame('SortA', $ordered[0]->title);
        $this->assertSame('SortB', $ordered[1]->title);
        $this->assertSame('SortC', $ordered[2]->title);
    }

    #[Test]
    #[TestDox('scopeRoot 只返回顶级菜单')]
    public function scope_root_returns_only_top_level(): void
    {
        $baseCount = AdminMenu::root()->count();
        $parent = $this->createMenu(['title' => '新父级']);
        $this->createMenu(['title' => '新子级', 'parent_id' => $parent->id]);

        $roots = AdminMenu::root()->get();

        $this->assertCount($baseCount + 1, $roots);
    }

    #[Test]
    #[TestDox('tree 返回顶级菜单带子菜单')]
    public function tree_returns_top_level_with_children(): void
    {
        $baseCount = AdminMenu::tree()->count();
        $parent = $this->createMenu(['title' => '树父级']);
        $this->createMenu(['title' => '树子级', 'parent_id' => $parent->id]);

        $tree = AdminMenu::tree();

        $this->assertCount($baseCount + 1, $tree);
    }

    #[Test]
    #[TestDox('tree onlyEnabled=true 只返回启用的顶级菜单')]
    public function tree_with_only_enabled_filters(): void
    {
        $baseCount = AdminMenu::tree(true)->count();
        $this->createMenu(['title' => '启用', 'is_enable' => true]);
        $this->createMenu(['title' => '禁用', 'is_enable' => false]);

        $tree = AdminMenu::tree(true);

        $this->assertCount($baseCount + 1, $tree);
    }

    #[Test]
    #[TestDox('保存菜单时同步权限到 permissions 表')]
    public function saved_syncs_permission(): void
    {
        $menu = $this->createMenu(['title' => '测试', 'permission' => 'test.permission']);

        $this->assertDatabaseHas('permissions', [
            'name' => 'test.permission',
            'guard_name' => AdminMenu::GUARD_NAME,
            'display_name' => '测试',
        ]);
    }

    #[Test]
    #[TestDox('删除菜单时同步删除孤立权限')]
    public function deleted_removes_orphaned_permission(): void
    {
        $menu = $this->createMenu(['title' => '测试', 'permission' => 'delete.me']);
        $this->assertDatabaseHas('permissions', ['name' => 'delete.me']);

        $menu->delete();

        $this->assertDatabaseMissing('permissions', ['name' => 'delete.me']);
    }

    #[Test]
    #[TestDox('toRouteRecord 禁用菜单返回 null')]
    public function to_route_record_disabled_returns_null(): void
    {
        $menu = $this->createMenu(['title' => '禁用', 'is_enable' => false]);

        $this->assertNull($menu->toRouteRecord());
    }

    #[Test]
    #[TestDox('toRouteRecord 按钮类型返回特殊标记')]
    public function to_route_record_button_returns_button_mark(): void
    {
        $menu = $this->createMenu([
            'title' => '按钮',
            'type' => MenuType::BUTTON,
            'permission' => 'btn.test',
        ]);

        $result = $menu->toRouteRecord();

        $this->assertTrue($result['__button']);
        $this->assertSame('按钮', $result['title']);
    }

    #[Test]
    #[TestDox('type 属性正确 cast 为 MenuType 枚举')]
    public function type_is_cast_to_enum(): void
    {
        $menu = $this->createMenu(['type' => MenuType::DIRECTORY]);

        $this->assertInstanceOf(MenuType::class, $menu->type);
        $this->assertSame(MenuType::DIRECTORY, $menu->type);
    }

    #[Test]
    #[TestDox('布尔属性正确 cast')]
    public function boolean_attributes_are_cast(): void
    {
        $menu = $this->createMenu([
            'is_enable' => true,
            'is_hide' => true,
            'keep_alive' => true,
        ]);

        $this->assertTrue($menu->is_enable);
        $this->assertTrue($menu->is_hide);
        $this->assertTrue($menu->keep_alive);
    }
}
