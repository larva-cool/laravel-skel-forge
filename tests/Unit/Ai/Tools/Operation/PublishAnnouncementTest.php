<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Operation;

use App\Ai\Tools\Operation\PublishAnnouncement;
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
 * 公告状态流转工具测试
 */
#[CoversClass(PublishAnnouncement::class)]
#[Group('ai')]
#[Group('operation')]
class PublishAnnouncementTest extends TestCase
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
        $result = (new PublishAnnouncement)->handle(new Request(['id' => 1, 'action' => 'publish']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 announcements.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.index']);
        $announcement = Announcement::factory()->create(['status' => AnnouncementStatus::DRAFT]);

        $result = (new PublishAnnouncement)->forAdmin($admin)->handle(new Request([
            'id' => $announcement->id,
            'action' => 'publish',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(AnnouncementStatus::DRAFT, $announcement->fresh()->status);
    }

    #[Test]
    #[TestDox('发布草稿公告后状态为已发布并记录发布时间')]
    public function publish_draft_sets_published_status_and_time(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.edit']);
        $announcement = Announcement::factory()->create([
            'status' => AnnouncementStatus::DRAFT,
            'published_at' => null,
        ]);

        $result = (new PublishAnnouncement)->forAdmin($admin)->handle(new Request([
            'id' => $announcement->id,
            'action' => 'publish',
        ]));

        $fresh = $announcement->fresh();
        $this->assertSame(AnnouncementStatus::PUBLISHED, $fresh->status);
        $this->assertNotNull($fresh->published_at);
        $this->assertStringContainsString('已发布公告', (string) $result);
    }

    #[Test]
    #[TestDox('归档已发布公告后状态为已归档')]
    public function archive_published_sets_archived_status(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.edit']);
        $announcement = Announcement::factory()->create(['status' => AnnouncementStatus::PUBLISHED]);

        $result = (new PublishAnnouncement)->forAdmin($admin)->handle(new Request([
            'id' => $announcement->id,
            'action' => 'archive',
        ]));

        $this->assertSame(AnnouncementStatus::ARCHIVED, $announcement->fresh()->status);
        $this->assertStringContainsString('已归档公告', (string) $result);
    }

    #[Test]
    #[TestDox('重复发布已发布公告时提示无需重复发布')]
    public function publish_skips_already_published(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.edit']);
        $announcement = Announcement::factory()->create(['status' => AnnouncementStatus::PUBLISHED]);

        $result = (new PublishAnnouncement)->forAdmin($admin)->handle(new Request([
            'id' => $announcement->id,
            'action' => 'publish',
        ]));

        $this->assertStringContainsString('无需重复发布', (string) $result);
    }

    #[Test]
    #[TestDox('草稿公告不可直接归档')]
    public function archive_rejects_draft(): void
    {
        $admin = $this->createAdminWithPermissions(['announcements.edit']);
        $announcement = Announcement::factory()->create(['status' => AnnouncementStatus::DRAFT]);

        $result = (new PublishAnnouncement)->forAdmin($admin)->handle(new Request([
            'id' => $announcement->id,
            'action' => 'archive',
        ]));

        $this->assertStringContainsString('仅已发布公告可归档', (string) $result);
        $this->assertSame(AnnouncementStatus::DRAFT, $announcement->fresh()->status);
    }
}
