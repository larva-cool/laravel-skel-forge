<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AttachmentType;
use App\Http\Controllers\Admin\AttachmentController;
use App\Models\Admin\Admin;
use App\Models\System\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台附件管理控制器功能测试
 */
#[CoversClass(AttachmentController::class)]
#[Group('admin')]
#[Group('attachment')]
class AttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
        Storage::fake('public');
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条附件台账并同步写入物理文件
     */
    private function createAttachment(array $attributes = [], bool $withFile = true): Attachment
    {
        /** @var Attachment $attachment */
        $attachment = Attachment::factory()->create($attributes);

        if ($withFile) {
            Storage::disk($attachment->disk)->put($attachment->path, 'dummy-content');
        }

        return $attachment;
    }

    #[Test]
    #[TestDox('未登录访问附件列表返回 401')]
    public function guest_cannot_list_attachments(): void
    {
        $this->getJson('/admin/attachments')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取磁盘列表返回全部磁盘名称与值')]
    public function admin_can_list_disks(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/attachments/disks');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['label', 'value', 'driver', 'is_default']]])
            ->assertJsonCount(count(config('filesystems.disks')), 'data')
            ->assertJsonPath('data.0.value', array_keys(config('filesystems.disks'))[0]);
    }

    #[Test]
    #[TestDox('未登录获取磁盘列表返回 401')]
    public function guest_cannot_list_disks(): void
    {
        $this->getJson('/admin/attachments/disks')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取附件列表返回分页结构')]
    public function admin_can_list_attachments(): void
    {
        $this->createAttachment();
        $this->createAttachment();

        $response = $this->actingAsAdmin()->getJson('/admin/attachments');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'original_name', 'disk', 'path', 'url', 'preview_url', 'type', 'extension', 'mime_type', 'size', 'size_text', 'created_at']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    #[Test]
    #[TestDox('私有磁盘附件列表返回临时签名预览地址')]
    public function private_disk_attachment_returns_signed_preview_url(): void
    {
        Storage::fake('local');
        $this->createAttachment(['disk' => 'local']);

        $response = $this->actingAsAdmin()->getJson('/admin/attachments');

        $response->assertOk()
            ->assertJsonPath('data.0.url', null);

        $this->assertNotNull($response->json('data.0.preview_url'));
    }

    #[Test]
    #[TestDox('按附件类型筛选列表')]
    public function admin_can_filter_attachments_by_type(): void
    {
        $this->createAttachment(['mime_type' => 'image/jpeg', 'extension' => 'jpg', 'type' => AttachmentType::IMAGE]);
        $this->createAttachment(['mime_type' => 'application/pdf', 'extension' => 'pdf', 'type' => AttachmentType::DOCUMENT]);

        $response = $this->actingAsAdmin()->getJson('/admin/attachments?type=image');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type.value', 'image');
    }

    #[Test]
    #[TestDox('按关键词与扩展名筛选列表')]
    public function admin_can_filter_attachments_by_keyword_and_extension(): void
    {
        $this->createAttachment(['name' => 'invoice-2026.pdf', 'extension' => 'pdf']);
        $this->createAttachment(['name' => 'poster.png', 'extension' => 'png']);

        $this->actingAsAdmin()->getJson('/admin/attachments?keyword=invoice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'invoice-2026.pdf');

        $this->actingAsAdmin()->getJson('/admin/attachments?extension=png')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'poster.png');
    }

    #[Test]
    #[TestDox('按创建时间范围筛选列表')]
    public function admin_can_filter_attachments_by_date_range(): void
    {
        $old = $this->createAttachment();
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        $this->createAttachment();

        $response = $this->actingAsAdmin()->getJson('/admin/attachments?start_date='.now()->subDay()->toDateString());

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    #[TestDox('非法筛选参数返回 422')]
    public function invalid_filter_returns_validation_error(): void
    {
        $this->actingAsAdmin()->getJson('/admin/attachments?type=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    #[TestDox('获取附件详情返回物理存在标识')]
    public function admin_can_show_attachment(): void
    {
        $attachment = $this->createAttachment();

        $response = $this->actingAsAdmin()->getJson("/admin/attachments/{$attachment->id}");

        $response->assertOk()
            ->assertJsonPath('id', $attachment->id)
            ->assertJsonPath('exists', true);
    }

    #[Test]
    #[TestDox('物理文件缺失时详情的 exists 为 false')]
    public function show_reports_missing_physical_file(): void
    {
        $attachment = $this->createAttachment(withFile: false);

        $this->actingAsAdmin()->getJson("/admin/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    #[Test]
    #[TestDox('获取不存在的附件详情返回 404')]
    public function show_missing_attachment_returns_not_found(): void
    {
        $this->actingAsAdmin()->getJson('/admin/attachments/99999999')->assertNotFound();
    }

    #[Test]
    #[TestDox('删除附件会同时删除物理文件并软删除台账')]
    public function admin_can_delete_attachment(): void
    {
        $attachment = $this->createAttachment();

        $this->actingAsAdmin()->deleteJson("/admin/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin.attachment_delete_success'));

        Storage::disk($attachment->disk)->assertMissing($attachment->path);
        $this->assertSoftDeleted($attachment);
    }

    #[Test]
    #[TestDox('物理文件缺失时删除附件仍然成功')]
    public function delete_succeeds_when_physical_file_missing(): void
    {
        $attachment = $this->createAttachment(withFile: false);

        $this->actingAsAdmin()->deleteJson("/admin/attachments/{$attachment->id}")->assertOk();

        $this->assertSoftDeleted($attachment);
    }

    #[Test]
    #[TestDox('批量删除附件返回删除条数')]
    public function admin_can_batch_delete_attachments(): void
    {
        $first = $this->createAttachment();
        $second = $this->createAttachment();

        $this->actingAsAdmin()->deleteJson('/admin/attachments', ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('count', 2);

        Storage::disk($first->disk)->assertMissing($first->path);
        Storage::disk($second->disk)->assertMissing($second->path);
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    #[Test]
    #[TestDox('批量删除缺少 ids 返回 422')]
    public function batch_delete_requires_ids(): void
    {
        $this->actingAsAdmin()->deleteJson('/admin/attachments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');
    }

    #[Test]
    #[TestDox('下载附件响应头包含原始文件名')]
    public function admin_can_download_attachment(): void
    {
        $attachment = $this->createAttachment(['original_name' => 'report.pdf']);

        $response = $this->actingAsAdmin()->get("/admin/attachments/{$attachment->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('report.pdf', $response->headers->get('content-disposition'));
    }

    #[Test]
    #[TestDox('物理文件缺失时下载返回 422')]
    public function download_missing_physical_file_returns_error(): void
    {
        $attachment = $this->createAttachment(withFile: false);

        $this->actingAsAdmin()->getJson("/admin/attachments/{$attachment->id}/download")->assertUnprocessable();
    }

    #[Test]
    #[TestDox('公开磁盘获取临时地址降级为直链')]
    public function temporary_url_falls_back_to_public_url(): void
    {
        $attachment = $this->createAttachment();

        $response = $this->actingAsAdmin()->getJson("/admin/attachments/{$attachment->id}/temporary-url");

        $response->assertOk();
        $this->assertNotEmpty($response->json('url'));
    }

    #[Test]
    #[TestDox('重命名附件仅修改显示名，不改动存储路径')]
    public function admin_can_rename_attachment(): void
    {
        $attachment = $this->createAttachment();
        $originalPath = $attachment->path;

        $this->actingAsAdmin()->putJson("/admin/attachments/{$attachment->id}/rename", ['name' => '新名称.jpg'])
            ->assertOk()
            ->assertJsonPath('name', '新名称.jpg')
            ->assertJsonPath('path', $originalPath);

        Storage::disk($attachment->disk)->assertExists($originalPath);
    }

    #[Test]
    #[TestDox('重命名缺少 name 返回 422')]
    public function rename_requires_name(): void
    {
        $attachment = $this->createAttachment();

        $this->actingAsAdmin()->putJson("/admin/attachments/{$attachment->id}/rename", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    #[TestDox('移动附件后新路径存在旧路径消失')]
    public function admin_can_move_attachment(): void
    {
        $attachment = $this->createAttachment();
        $oldPath = $attachment->path;
        $newPath = 'uploads/moved/target.jpg';

        $this->actingAsAdmin()->putJson("/admin/attachments/{$attachment->id}/move", ['path' => $newPath])
            ->assertOk()
            ->assertJsonPath('path', $newPath);

        Storage::disk($attachment->disk)->assertExists($newPath);
        Storage::disk($attachment->disk)->assertMissing($oldPath);
    }

    #[Test]
    #[TestDox('移动到已存在的路径返回 422')]
    public function move_to_existing_path_returns_error(): void
    {
        $attachment = $this->createAttachment();
        $occupied = 'uploads/moved/occupied.jpg';
        Storage::disk($attachment->disk)->put($occupied, 'occupied');

        $this->actingAsAdmin()->putJson("/admin/attachments/{$attachment->id}/move", ['path' => $occupied])
            ->assertUnprocessable();
    }

    #[Test]
    #[TestDox('移动到非法前缀路径返回 422')]
    public function move_to_invalid_path_returns_error(): void
    {
        $attachment = $this->createAttachment();

        $this->actingAsAdmin()->putJson("/admin/attachments/{$attachment->id}/move", ['path' => 'private/hack.jpg'])
            ->assertUnprocessable();
    }

    #[Test]
    #[TestDox('直传登记会写入附件台账')]
    public function admin_can_register_uploaded_file(): void
    {
        $path = 'uploads/2026/08/17/direct.png';
        Storage::disk('public')->put($path, 'direct-upload');

        $response = $this->actingAsAdmin()->postJson('/admin/attachments/register', [
            'path' => $path,
            'disk' => 'public',
            'original_name' => 'direct.png',
        ]);

        $response->assertCreated()->assertJsonPath('path', $path);
        $this->assertDatabaseHas('attachments', ['disk' => 'public', 'path' => $path]);
    }

    #[Test]
    #[TestDox('直传登记时物理文件不存在返回 422')]
    public function register_rejects_missing_physical_file(): void
    {
        $this->actingAsAdmin()->postJson('/admin/attachments/register', [
            'path' => 'uploads/2026/08/17/ghost.png',
            'disk' => 'public',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');
    }

    #[Test]
    #[TestDox('直传登记时非法路径返回 422')]
    public function register_rejects_invalid_path(): void
    {
        Storage::disk('public')->put('private/ghost.png', 'x');

        $this->actingAsAdmin()->postJson('/admin/attachments/register', [
            'path' => 'private/ghost.png',
            'disk' => 'public',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');
    }

    #[Test]
    #[TestDox('通过上传接口上传文件会自动写入附件台账')]
    public function upload_endpoint_records_attachment(): void
    {
        settings()->set('upload.storage', 'public');

        $response = $this->actingAsAdmin()->postJson('/admin/uploader/file', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertOk()->assertJsonStructure(['id', 'storage', 'file_path', 'file_name', 'url']);
        $this->assertDatabaseHas('attachments', ['id' => $response->json('id'), 'original_name' => 'photo.jpg']);
    }

    #[Test]
    #[TestDox('图片上传接口返回可直接预览的访问地址')]
    public function image_upload_endpoint_returns_preview_url(): void
    {
        settings()->set('upload.storage', 'public');

        $response = $this->actingAsAdmin()->postJson('/admin/uploader/image', [
            'file' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('url'));
        $this->assertStringContainsString($response->json('file_name'), (string) $response->json('url'));
    }

    #[Test]
    #[TestDox('通过上传接口上传非图片文件会自动写入附件台账')]
    public function upload_endpoint_accepts_non_image_file(): void
    {
        settings()->set('upload.storage', 'public');

        $response = $this->actingAsAdmin()->postJson('/admin/uploader/file', [
            'file' => UploadedFile::fake()->create('report.pdf', 16, 'application/pdf'),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attachments', [
            'id' => $response->json('id'),
            'original_name' => 'report.pdf',
            'extension' => 'pdf',
        ]);
    }

    #[Test]
    #[TestDox('通过图片上传接口上传图片会自动写入附件台账')]
    public function image_upload_endpoint_records_attachment(): void
    {
        settings()->set('upload.storage', 'public');
        settings()->set('upload.optimize_image', false);

        $response = $this->actingAsAdmin()->postJson('/admin/uploader/image', [
            'file' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attachments', [
            'id' => $response->json('id'),
            'original_name' => 'avatar.png',
        ]);
    }

    #[Test]
    #[TestDox('通过视频上传接口上传视频会自动写入附件台账')]
    public function video_upload_endpoint_records_attachment(): void
    {
        settings()->set('upload.storage', 'public');

        $response = $this->actingAsAdmin()->postJson('/admin/uploader/video', [
            'file' => UploadedFile::fake()->create('clip.mp4', 32, 'video/mp4'),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attachments', [
            'id' => $response->json('id'),
            'original_name' => 'clip.mp4',
            'extension' => 'mp4',
        ]);
    }
}
