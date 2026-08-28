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
 * user.segment 子菜单，并自动产生了 5 条 segments.* 权限，超级管理员持有全部。
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
    #[TestDox('user.segment 子菜单已注册到 user 顶级菜单下')]
    public function user_segment_menu_registered(): void
    {
        $userMenu = AdminMenu::query()->where('path', 'segment')->where('component', '/user/segment')->first();
        $this->assertNotNull($userMenu, 'user.segment 菜单未注册');
        $this->assertSame('分群管理', $userMenu->title);
        $this->assertSame('segments.index', $userMenu->permission);
        $this->assertTrue($userMenu->is_enable);

        // 父菜单是 user 顶级菜单（path=/user）
        $parent = $userMenu->parent()->first();
        $this->assertNotNull($parent);
        $this->assertSame('用户管理', $parent->title);
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
    #[TestDox('4 个 button 菜单挂载到 user.segment 下')]
    public function segment_buttons_attached(): void
    {
        $userMenu = AdminMenu::query()
            ->where('path', 'segment')
            ->where('component', '/user/segment')
            ->firstOrFail();
        $buttons = AdminMenu::query()
            ->where('parent_id', $userMenu->id)
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
