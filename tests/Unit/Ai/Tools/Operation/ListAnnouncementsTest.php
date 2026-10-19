<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Operation;

use App\Ai\Tools\Operation\ListAnnouncements;
use App\Enums\AdminStatus;
use App\Enums\AnnouncementStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Operation\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 公告列表查询工具测试
 */
#[CoversClass(ListAnnouncements::class)]
#[Group('ai')]
#[Group('operation')]
class ListAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListAnnouncements)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 announcements.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListAnnouncements)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页公告数据')]
    public function handle_returns_paginated_announcements(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.index']);
        Announcement::factory()->count(2)->create();

        $result = (new ListAnnouncements)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('status 筛选仅返回对应状态公告')]
    public function handle_filters_by_status(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.index']);
        Announcement::factory()->create(['status' => AnnouncementStatus::DRAFT]);
        Announcement::factory()->create(['status' => AnnouncementStatus::PUBLISHED]);

        $result = (new ListAnnouncements)->forAdmin($admin)->handle(new Request(['status' => 'published']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('published', $payload['data'][0]['status']);
    }

    #[Test]
    #[TestDox('keyword 筛选匹配标题')]
    public function handle_filters_by_keyword(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.index']);
        Announcement::factory()->create(['title' => '国庆活动公告']);
        Announcement::factory()->create(['title' => '系统升级通知']);

        $result = (new ListAnnouncements)->forAdmin($admin)->handle(new Request(['keyword' => '国庆']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('国庆活动公告', $payload['data'][0]['title']);
    }
}
