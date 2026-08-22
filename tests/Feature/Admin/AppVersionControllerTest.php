<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppPlatform;
use App\Enums\ReleaseStatus;
use App\Http\Controllers\Admin\AppVersionController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\App\AppRelease;
use App\Models\App\AppVersion;
use App\Models\System\Permission;
use App\Models\System\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台版本管理控制器功能测试
 */
#[CoversClass(AppVersionController::class)]
#[Group('admin')]
#[Group('app-version')]
class AppVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private AppRelease $appRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
        $this->appRelease = AppRelease::factory()->create();

        // 为版本管理创建权限并赋给超级管理员
        $permissions = ['app-versions.index', 'app-versions.create', 'app-versions.edit', 'app-versions.delete'];
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, AdminMenu::GUARD_NAME);
        }
        /** @var Role $superRole */
        $superRole = Role::where('name', 'super_admin')->firstOrFail();
        $superRole->givePermissionTo($permissions);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问版本列表返回 401')]
    public function guest_cannot_list_versions(): void
    {
        $this->getJson("/admin/apps/{$this->appRelease->id}/versions")->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取版本列表返回 200 与分页数据')]
    public function admin_can_list_versions(): void
    {
        AppVersion::factory()->count(3)->create(['app_release_id' => $this->appRelease->id]);

        $this->actingAsAdmin()->getJson("/admin/apps/{$this->appRelease->id}/versions")
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按平台过滤版本')]
    public function admin_can_filter_versions_by_platform(): void
    {
        AppVersion::factory()->create(['app_release_id' => $this->appRelease->id, 'platform' => AppPlatform::ANDROID]);
        AppVersion::factory()->create(['app_release_id' => $this->appRelease->id, 'platform' => AppPlatform::IOS]);

        $this->actingAsAdmin()->getJson("/admin/apps/{$this->appRelease->id}/versions?platform=ios")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.platform.value', 'ios');
    }

    #[Test]
    #[TestDox('创建版本返回 201')]
    public function admin_can_create_version(): void
    {
        $response = $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions", [
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
            'download_url' => 'https://example.com/app.apk',
            'release_notes' => '首次发布',
        ]);

        $response->assertCreated()
            ->assertJsonPath('version', '1.0.0')
            ->assertJsonPath('build', 10000)
            ->assertJsonPath('platform.value', 'android');

        $this->assertDatabaseHas('app_versions', [
            'app_release_id' => $this->appRelease->id,
            'version' => '1.0.0',
            'build' => 10000,
        ]);
    }

    #[Test]
    #[TestDox('创建版本时版本号格式不正确返回 422')]
    public function create_version_invalid_version_format(): void
    {
        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions", [
            'platform' => 'android',
            'version' => 'invalid',
            'build' => 10000,
            'download_url' => 'https://example.com/app.apk',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    #[Test]
    #[TestDox('同一应用同平台同构建号不可重复返回 422')]
    public function create_version_build_must_be_unique_per_app_platform(): void
    {
        AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10000,
        ]);

        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions", [
            'platform' => 'android',
            'version' => '1.0.1',
            'build' => 10000,
            'download_url' => 'https://example.com/app.apk',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['build']);
    }

    #[Test]
    #[TestDox('获取版本详情')]
    public function admin_can_view_version(): void
    {
        $version = AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'version' => '2.0.0',
        ]);

        $this->actingAsAdmin()->getJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}")
            ->assertOk()
            ->assertJsonPath('id', $version->id)
            ->assertJsonPath('version', '2.0.0');
    }

    #[Test]
    #[TestDox('更新版本成功')]
    public function admin_can_update_version(): void
    {
        $version = AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'release_notes' => '旧说明',
        ]);

        $this->actingAsAdmin()->putJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}", [
            'platform' => $version->platform->value,
            'version' => $version->version,
            'build' => $version->build,
            'download_url' => $version->download_url,
            'release_notes' => '新说明',
        ])->assertOk()
            ->assertJsonPath('release_notes', '新说明');
    }

    #[Test]
    #[TestDox('发布版本自动废弃同平台旧已发布版本')]
    public function publish_version_obsoletes_old_published(): void
    {
        $oldVersion = AppVersion::factory()->published()->create([
            'app_release_id' => $this->appRelease->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10000,
        ]);
        $newVersion = AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
        ]);

        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions/{$newVersion->id}/publish")
            ->assertOk()
            ->assertJsonPath('status.value', 'published');

        $oldVersion->refresh();
        $this->assertTrue($oldVersion->status->isObsolete());
    }

    #[Test]
    #[TestDox('撤回已发布版本')]
    public function admin_can_withdraw_published_version(): void
    {
        $version = AppVersion::factory()->published()->create([
            'app_release_id' => $this->appRelease->id,
        ]);

        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('status.value', 'withdrawn');
    }

    #[Test]
    #[TestDox('撤回非已发布版本返回 400')]
    public function cannot_withdraw_non_published_version(): void
    {
        $version = AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'status' => ReleaseStatus::DRAFT,
        ]);

        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}/withdraw")
            ->assertBadRequest();
    }

    #[Test]
    #[TestDox('废弃版本')]
    public function admin_can_obsolete_version(): void
    {
        $version = AppVersion::factory()->published()->create([
            'app_release_id' => $this->appRelease->id,
        ]);

        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}/obsolete")
            ->assertOk()
            ->assertJsonPath('status.value', 'obsolete');
    }

    #[Test]
    #[TestDox('已废弃版本再次废弃返回 400')]
    public function cannot_obsolete_already_obsolete_version(): void
    {
        $version = AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'status' => ReleaseStatus::OBSOLETE,
        ]);

        $this->actingAsAdmin()->postJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}/obsolete")
            ->assertBadRequest();
    }

    #[Test]
    #[TestDox('删除已发布版本返回 400')]
    public function cannot_delete_published_version(): void
    {
        $version = AppVersion::factory()->published()->create([
            'app_release_id' => $this->appRelease->id,
        ]);

        $this->actingAsAdmin()->deleteJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}")
            ->assertBadRequest();
    }

    #[Test]
    #[TestDox('删除草稿版本返回 204')]
    public function admin_can_delete_draft_version(): void
    {
        $version = AppVersion::factory()->create([
            'app_release_id' => $this->appRelease->id,
            'status' => ReleaseStatus::DRAFT,
        ]);

        $this->actingAsAdmin()->deleteJson("/admin/apps/{$this->appRelease->id}/versions/{$version->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('app_versions', ['id' => $version->id]);
    }
}
