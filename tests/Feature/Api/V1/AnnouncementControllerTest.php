<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Models\Operation\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台公开公告接口功能测试
 */
#[CoversClass(AnnouncementController::class)]
#[Group('api')]
#[Group('announcement')]
class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('前台可获取已发布公告列表')]
    public function guest_can_list_published_announcements(): void
    {
        Announcement::factory()->count(2)->published()->create();
        Announcement::factory()->draft()->create();
        Announcement::factory()->archived()->create();

        $response = $this->getJson('/api/v1/announcements');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('前台列表不返回 content 字段')]
    public function list_response_excludes_content(): void
    {
        Announcement::factory()->published()->create();

        $response = $this->getJson('/api/v1/announcements');
        $data = $response->json('data.0');

        $this->assertArrayNotHasKey('content', $data);
    }

    #[Test]
    #[TestDox('前台列表返回公开字段')]
    public function list_response_includes_public_fields(): void
    {
        Announcement::factory()->published()->create();

        $response = $this->getJson('/api/v1/announcements');
        $data = $response->json('data.0');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('cover_image', $data);
        $this->assertArrayHasKey('published_at', $data);
    }

    #[Test]
    #[TestDox('前台可查看已发布公告详情')]
    public function guest_can_view_published_announcement_detail(): void
    {
        $announcement = Announcement::factory()->published()->create();

        $response = $this->getJson("/api/v1/announcements/{$announcement->id}");

        $response->assertOk()
            ->assertJsonPath('id', $announcement->id)
            ->assertJsonStructure(['content']);
    }

    #[Test]
    #[TestDox('前台查看草稿公告返回 404')]
    public function guest_cannot_view_draft_announcement(): void
    {
        $announcement = Announcement::factory()->draft()->create();

        $this->getJson("/api/v1/announcements/{$announcement->id}")
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('前台查看已归档公告返回 404')]
    public function guest_cannot_view_archived_announcement(): void
    {
        $announcement = Announcement::factory()->archived()->create();

        $this->getJson("/api/v1/announcements/{$announcement->id}")
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('前台查看不存在的公告返回 404')]
    public function guest_view_missing_announcement_returns_404(): void
    {
        $this->getJson('/api/v1/announcements/999999')
            ->assertNotFound();
    }
}
