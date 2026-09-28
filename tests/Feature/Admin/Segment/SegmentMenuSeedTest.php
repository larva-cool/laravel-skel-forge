<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * seed 迁移中分群菜单与权限注册测试
 *
 * 验证 2026_07_31_111934_seed_initial_admin_data.php 在 user 顶级菜单下注册了
 * user.segment-group 目录菜单，其下包含分群管理、分群成员、分群任务三个子菜单，
 * 并自动产生了 segments.* 权限，超级管理员持有全部。
 */
#[Group('admin')]
#[Group('segment')]
class SegmentMenuSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 触发 seed 迁移（admin 用户 + 角色 + 菜单 + 权限）
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    #[Test]
    #[TestDox('user.segment-group 目录菜单已注册到 user 顶级菜单下，包含分群管理、分群成员、分群任务三个子菜单')]
    public function user_segment_group_menu_registered(): void
    {
        // 查找"用户分群"目录菜单
        $groupMenu = AdminMenu::query()
            ->where('path', 'segment-group')
            ->where('type', \App\Enums\MenuType::DIRECTORY)
            ->first();
        $this->assertNotNull($groupMenu, 'user.segment-group 目录菜单未注册');
        $this->assertSame('用户分群', $groupMenu->title);

        // 父菜单是 user 顶级菜单（path=/user）
        $parent = $groupMenu->parent()->first();
        $this->assertNotNull($parent);
        $this->assertSame('用户管理', $parent->title);

        // 验证子菜单
        $children = AdminMenu::query()
            ->where('parent_id', $groupMenu->id)
            ->where('type', \App\Enums\MenuType::MENU)
            ->orderBy('sort')
            ->get();
        $this->assertCount(3, $children);

        $this->assertSame('分群管理', $children[0]->title);
        $this->assertSame('segment', $children[0]->path);
        $this->assertSame('/user/segment', $children[0]->component);
        $this->assertSame('segments.index', $children[0]->permission);

        $this->assertSame('分群成员', $children[1]->title);
        $this->assertSame('member', $children[1]->path);
        $this->assertSame('/user/segment-member', $children[1]->component);
        $this->assertSame('segments.index', $children[1]->permission);

        $this->assertSame('分群任务', $children[2]->title);
        $this->assertSame('task', $children[2]->path);
        $this->assertSame('/user/segment-task', $children[2]->component);
        $this->assertSame('segments.task', $children[2]->permission);
    }

    #[Test]
    #[TestDox('5 条 segments.* 权限均已自动创建')]
    public function segment_permissions_created(): void
    {
        $expected = [
            'segments.index',
            'segments.create',
            'segments.edit',
            'segments.delete',
            'segments.assign',
        ];
        foreach ($expected as $name) {
            $this->assertTrue(
                Permission::where('name', $name)->where('guard_name', 'admin')->exists(),
                "权限 {$name} 未创建"
            );
        }
    }

    #[Test]
    #[TestDox('4 个 button 菜单挂载到分群管理菜单下')]
    public function segment_buttons_attached(): void
    {
        $segmentMenu = AdminMenu::query()
            ->where('path', 'segment')
            ->where('component', '/user/segment')
            ->firstOrFail();
        $buttons = AdminMenu::query()
            ->where('parent_id', $segmentMenu->id)
            ->where('type', \App\Enums\MenuType::BUTTON)
            ->get();

        $this->assertCount(4, $buttons);
        $permissionMap = $buttons->pluck('permission')->all();
        sort($permissionMap);
        $this->assertSame(
            ['segments.assign', 'segments.create', 'segments.delete', 'segments.edit'],
            $permissionMap
        );
    }

    #[Test]
    #[TestDox('分群成员菜单挂载 1 个按钮（移出分群）')]
    public function segment_member_buttons_attached(): void
    {
        $memberMenu = AdminMenu::query()
            ->where('path', 'member')
            ->where('component', '/user/segment-member')
            ->firstOrFail();
        $buttons = AdminMenu::query()
            ->where('parent_id', $memberMenu->id)
            ->where('type', \App\Enums\MenuType::BUTTON)
            ->get();

        $this->assertCount(1, $buttons);
        $this->assertSame('segments.assign', $buttons->first()->permission);
    }

    #[Test]
    #[TestDox('超级管理员（admin）持有 segments.* 全套权限')]
    public function super_admin_has_segment_permissions(): void
    {
        $admin = Admin::query()->where('username', 'admin')->firstOrFail();

        foreach (['index', 'create', 'edit', 'delete', 'assign'] as $action) {
            $this->assertTrue(
                $admin->can("segments.{$action}"),
                "超级管理员缺少 segments.{$action} 权限"
            );
        }
    }
}
