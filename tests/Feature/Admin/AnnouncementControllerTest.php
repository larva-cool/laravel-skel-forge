<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Models\Admin\Admin;
use App\Models\Operation\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台公告管理控制器功能测试
 */
#[CoversClass(AnnouncementController::class)]
#[Group('admin')]
#[Group('announcement')]
class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 默认有效 payload
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '国庆节放假通知',
            'summary' => '根据国务院规定，国庆节放假7天。',
            'content' => '<p>各位同事：</p><p>根据国务院办公厅通知，2026年国庆节放假安排如下：10月1日至10月7日放假调休，共7天。</p>',
            'status' => AnnouncementStatus::DRAFT->value,
            'sort' => 100,
            'cover_image' => null,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问公告列表返回 401')]
    public function guest_cannot_list_announcements(): void
    {
        $this->getJson('/admin/announcements')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取公告列表分页数据')]
    public function admin_can_list_announcements(): void
    {
        Announcement::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/announcements');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按状态筛选公告')]
    public function admin_can_filter_by_status(): void
    {
        Announcement::factory()->count(2)->draft()->create();
        Announcement::factory()->count(3)->published()->create();
        Announcement::factory()->count(2)->archived()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/announcements?status='.AnnouncementStatus::PUBLISHED->value);

        $response->assertOk()->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按关键词搜索公告标题')]
    public function admin_can_search_by_keyword(): void
    {
        Announcement::factory()->create(['title' => '春节放假通知']);
        Announcement::factory()->create(['title' => '系统维护公告']);

        $response = $this->actingAsAdmin()->getJson('/admin/announcements?keyword='.rawurlencode('春节'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('列表返回数据包含 status 和 status_label 字段')]
    public function list_response_includes_status_fields(): void
    {
        Announcement::factory()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/announcements');
        $data = $response->json('data.0');

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('status_label', $data);
        $this->assertArrayHasKey('status_label_type', $data);
    }

    #[Test]
    #[TestDox('列表不返回 content 字段')]
    public function list_response_excludes_content(): void
    {
        Announcement::factory()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/announcements');
        $data = $response->json('data.0');

        $this->assertArrayNotHasKey('content', $data);
    }

    #[Test]
    #[TestDox('详情返回 content 字段')]
    public function show_response_includes_content(): void
    {
        $announcement = Announcement::factory()->create();

        $response = $this->actingAsAdmin()->getJson("/admin/announcements/{$announcement->id}");

        $response->assertOk()
            ->assertJsonPath('id', $announcement->id)
            ->assertJsonStructure(['content']);
    }

    #[Test]
    #[TestDox('管理员可创建公告')]
    public function admin_can_create_announcement(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/announcements', $this->validPayload([
            'title' => '元旦放假通知',
        ]));

        $response->assertCreated()
            ->assertJsonPath('title', '元旦放假通知')
            ->assertJsonPath('status', AnnouncementStatus::DRAFT->value);

        $this->assertDatabaseHas('announcements', [
            'title' => '元旦放假通知',
            'status' => AnnouncementStatus::DRAFT->value,
        ]);
    }

    #[Test]
    #[TestDox('创建公告时直接设置 published 状态自动写入 published_at')]
    public function create_with_published_status_sets_published_at(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/announcements', $this->validPayload([
            'status' => AnnouncementStatus::PUBLISHED->value,
        ]));

        $response->assertCreated();

        $this->assertNotNull($response->json('published_at'));
    }

    #[Test]
    #[TestDox('创建公告时缺少 title 字段返回 422')]
    public function create_requires_title(): void
    {
        $payload = $this->validPayload();
        unset($payload['title']);

        $this->actingAsAdmin()->postJson('/admin/announcements', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    #[TestDox('创建公告时缺少 content 字段返回 422')]
    public function create_requires_content(): void
    {
        $payload = $this->validPayload();
        unset($payload['content']);

        $this->actingAsAdmin()->postJson('/admin/announcements', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    #[Test]
    #[TestDox('创建公告时 status 非法返回 422')]
    public function create_validates_status_enum(): void
    {
        $this->actingAsAdmin()->postJson('/admin/announcements', $this->validPayload([
            'status' => 'invalid_status',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    #[TestDox('管理员可查看公告详情')]
    public function admin_can_view_announcement_detail(): void
    {
        $announcement = Announcement::factory()->create(['title' => '详情测试']);

        $response = $this->actingAsAdmin()->getJson("/admin/announcements/{$announcement->id}");

        $response->assertOk()
            ->assertJsonPath('id', $announcement->id)
            ->assertJsonPath('title', '详情测试');
    }

    #[Test]
    #[TestDox('查看不存在公告返回 404')]
    public function view_missing_announcement_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/announcements/999999')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('管理员可更新公告')]
    public function admin_can_update_announcement(): void
    {
        $announcement = Announcement::factory()->create(['title' => '原标题']);

        $response = $this->actingAsAdmin()->putJson("/admin/announcements/{$announcement->id}", $this->validPayload([
            'title' => '更新后的标题',
            'sort' => 99,
        ]));

        $response->assertOk()
            ->assertJsonPath('title', '更新后的标题')
            ->assertJsonPath('sort', 99);

        $announcement->refresh();
        $this->assertSame('更新后的标题', $announcement->title);
        $this->assertSame(99, $announcement->sort);
    }

    #[Test]
    #[TestDox('更新时校验失败返回 422 且不改数据库')]
    public function update_with_invalid_data_does_not_modify_record(): void
    {
        $announcement = Announcement::factory()->create(['title' => '原标题']);

        $this->actingAsAdmin()->putJson("/admin/announcements/{$announcement->id}", [
            'title' => '', // 必填，不能为空
        ])->assertStatus(422);

        $announcement->refresh();
        $this->assertSame('原标题', $announcement->title);
    }

    #[Test]
    #[TestDox('切换公告状态：草稿 → 已发布')]
    public function toggle_status_from_draft_to_published(): void
    {
        $announcement = Announcement::factory()->draft()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/announcements/{$announcement->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('status', AnnouncementStatus::PUBLISHED->value);

        $announcement->refresh();
        $this->assertSame(AnnouncementStatus::PUBLISHED, $announcement->status);
        $this->assertNotNull($announcement->published_at);
    }

    #[Test]
    #[TestDox('切换公告状态：已发布 → 已归档')]
    public function toggle_status_from_published_to_archived(): void
    {
        $announcement = Announcement::factory()->published()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/announcements/{$announcement->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('status', AnnouncementStatus::ARCHIVED->value);

        $announcement->refresh();
        $this->assertSame(AnnouncementStatus::ARCHIVED, $announcement->status);
    }

    #[Test]
    #[TestDox('切换公告状态：已归档 → 已发布')]
    public function toggle_status_from_archived_to_published(): void
    {
        $announcement = Announcement::factory()->archived()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/announcements/{$announcement->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('status', AnnouncementStatus::PUBLISHED->value);

        $announcement->refresh();
        $this->assertSame(AnnouncementStatus::PUBLISHED, $announcement->status);
    }

    #[Test]
    #[TestDox('管理员可删除公告')]
    public function admin_can_delete_announcement(): void
    {
        $announcement = Announcement::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/announcements/{$announcement->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
    }
}
