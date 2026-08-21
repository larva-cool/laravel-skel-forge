<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MenuController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台菜单管理控制器功能测试
 */
#[CoversClass(MenuController::class)]
#[Group('admin')]
#[Group('menu')]
class MenuControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function menuData(array $overrides = []): array
    {
        return array_merge([
            'title' => '测试菜单',
            'type' => 1,
            'sort' => 0,
            'is_enable' => true,
            'is_hide' => false,
            'is_hide_tab' => false,
            'is_iframe' => false,
            'keep_alive' => false,
            'is_full_page' => false,
            'fixed_tab' => false,
            'show_badge' => false,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问菜单列表返回 401')]
    public function guest_cannot_list_menus(): void
    {
        $this->getJson('/admin/menus')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取菜单树形列表返回 200')]
    public function admin_can_list_menu_tree(): void
    {
        $parent = AdminMenu::create($this->menuData(['title' => '父级菜单']));
        AdminMenu::create($this->menuData(['title' => '子菜单', 'parent_id' => $parent->id]));

        $response = $this->actingAsAdmin()->getJson('/admin/menus');

        $response->assertOk()
            ->assertJsonPath('0.title', '父级菜单')
            ->assertJsonPath('0.children.0.title', '子菜单');
    }

    #[Test]
    #[TestDox('创建菜单返回 201')]
    public function admin_can_create_menu(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/menus', $this->menuData([
            'title' => '新菜单',
            'path' => '/new-menu',
            'name' => 'new_menu',
        ]));

        $response->assertCreated()
            ->assertJsonPath('title', '新菜单')
            ->assertJsonPath('path', '/new-menu');

        $this->assertDatabaseHas('admin_menus', ['title' => '新菜单', 'name' => 'new_menu']);
    }

    #[Test]
    #[TestDox('创建子菜单')]
    public function admin_can_create_child_menu(): void
    {
        $parent = AdminMenu::create($this->menuData(['title' => '父级']));

        $response = $this->actingAsAdmin()->postJson('/admin/menus', $this->menuData([
            'title' => '子级',
            'parent_id' => $parent->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('parent_id', $parent->id);
    }

    #[Test]
    #[TestDox('创建菜单时标题必填返回 422')]
    public function create_menu_requires_title(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/menus', $this->menuData([
            'title' => null,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    #[TestDox('创建菜单时路由名称不能重复返回 422')]
    public function create_menu_name_must_be_unique(): void
    {
        AdminMenu::create($this->menuData(['name' => 'duplicate_name']));

        $response = $this->actingAsAdmin()->postJson('/admin/menus', $this->menuData([
            'name' => 'duplicate_name',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    #[TestDox('获取菜单详情')]
    public function admin_can_view_menu(): void
    {
        $menu = AdminMenu::create($this->menuData(['title' => '详情测试']));

        $response = $this->actingAsAdmin()->getJson("/admin/menus/{$menu->id}");
        $response->assertOk()
            ->assertJsonPath('id', $menu->id)
            ->assertJsonPath('title', '详情测试');
    }

    #[Test]
    #[TestDox('更新菜单成功')]
    public function admin_can_update_menu(): void
    {
        $menu = AdminMenu::create($this->menuData(['title' => '旧标题']));

        $response = $this->actingAsAdmin()->putJson("/admin/menus/{$menu->id}", $this->menuData([
            'title' => '新标题',
            'path' => '/updated',
        ]));

        $response->assertOk()
            ->assertJsonPath('title', '新标题')
            ->assertJsonPath('path', '/updated');
    }

    #[Test]
    #[TestDox('更新菜单时不能将自身设为父级返回 422')]
    public function update_menu_cannot_set_self_as_parent(): void
    {
        $menu = AdminMenu::create($this->menuData(['title' => '自引用']));

        $response = $this->actingAsAdmin()->putJson("/admin/menus/{$menu->id}", $this->menuData([
            'title' => '自引用',
            'parent_id' => $menu->id,
        ]));

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('更新菜单时不能将后代设为父级返回 422')]
    public function update_menu_cannot_set_descendant_as_parent(): void
    {
        $parent = AdminMenu::create($this->menuData(['title' => '父级']));
        $child = AdminMenu::create($this->menuData(['title' => '子级', 'parent_id' => $parent->id]));

        $response = $this->actingAsAdmin()->putJson("/admin/menus/{$parent->id}", $this->menuData([
            'title' => '父级',
            'parent_id' => $child->id,
        ]));

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('删除无子菜单返回 204')]
    public function admin_can_delete_menu_without_children(): void
    {
        $menu = AdminMenu::create($this->menuData(['title' => '待删除']));

        $response = $this->actingAsAdmin()->deleteJson("/admin/menus/{$menu->id}");
        $response->assertNoContent();

        $this->assertDatabaseMissing('admin_menus', ['id' => $menu->id]);
    }

    #[Test]
    #[TestDox('有子菜单时不可删除返回 400')]
    public function cannot_delete_menu_with_children(): void
    {
        $parent = AdminMenu::create($this->menuData(['title' => '有子菜单']));
        AdminMenu::create($this->menuData(['title' => '子菜单', 'parent_id' => $parent->id]));

        $response = $this->actingAsAdmin()->deleteJson("/admin/menus/{$parent->id}");
        $response->assertBadRequest();
    }
}
