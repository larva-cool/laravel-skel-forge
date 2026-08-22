<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AppReleaseController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\App\AppRelease;
use App\Models\System\Permission;
use App\Models\System\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台应用管理控制器功能测试
 */
#[CoversClass(AppReleaseController::class)]
#[Group('admin')]
#[Group('app-release')]
class AppReleaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();

        // 为应用管理创建权限并赋给超级管理员
        $permissions = ['apps.index', 'apps.create', 'apps.edit', 'apps.delete'];
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
    #[TestDox('未登录访问应用列表返回 401')]
    public function guest_cannot_list_apps(): void
    {
        $this->getJson('/admin/apps')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取应用列表返回 200 与分页数据')]
    public function admin_can_list_apps(): void
    {
        AppRelease::factory()->count(3)->create();

        $this->actingAsAdmin()->getJson('/admin/apps')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按关键词搜索应用')]
    public function admin_can_search_apps_by_keyword(): void
    {
        AppRelease::factory()->create(['name' => 'TestApp', 'slug' => 'test-app']);
        AppRelease::factory()->create(['name' => 'OtherApp', 'slug' => 'other-app']);

        $this->actingAsAdmin()->getJson('/admin/apps?keyword=Test')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'TestApp');
    }

    #[Test]
    #[TestDox('创建应用返回 201')]
    public function admin_can_create_app(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/apps', [
            'name' => '测试应用',
            'slug' => 'test-app',
            'description' => '这是一个测试应用',
            'sort' => 10,
            'status' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', '测试应用')
            ->assertJsonPath('slug', 'test-app');

        $this->assertDatabaseHas('app_releases', ['name' => '测试应用', 'slug' => 'test-app']);
    }

    #[Test]
    #[TestDox('创建应用时名称必填返回 422')]
    public function create_app_requires_name(): void
    {
        $this->actingAsAdmin()->postJson('/admin/apps', ['slug' => 'test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    #[TestDox('创建应用时 slug 必须唯一返回 422')]
    public function create_app_slug_must_be_unique(): void
    {
        AppRelease::factory()->create(['slug' => 'duplicate-slug']);

        $this->actingAsAdmin()->postJson('/admin/apps', [
            'name' => '测试',
            'slug' => 'duplicate-slug',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    #[TestDox('创建应用时 slug 格式不正确返回 422')]
    public function create_app_slug_format_invalid(): void
    {
        $this->actingAsAdmin()->postJson('/admin/apps', [
            'name' => '测试',
            'slug' => 'Invalid Slug',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    #[TestDox('获取应用详情')]
    public function admin_can_view_app(): void
    {
        $app = AppRelease::factory()->create(['name' => '详情应用']);

        $this->actingAsAdmin()->getJson("/admin/apps/{$app->id}")
            ->assertOk()
            ->assertJsonPath('id', $app->id)
            ->assertJsonPath('name', '详情应用');
    }

    #[Test]
    #[TestDox('获取不存在的应用返回 404')]
    public function view_nonexistent_app_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/apps/99999999')->assertNotFound();
    }

    #[Test]
    #[TestDox('更新应用成功')]
    public function admin_can_update_app(): void
    {
        $app = AppRelease::factory()->create(['name' => '旧名称']);

        $this->actingAsAdmin()->putJson("/admin/apps/{$app->id}", [
            'name' => '新名称',
            'slug' => $app->slug,
            'sort' => 20,
        ])->assertOk()
            ->assertJsonPath('name', '新名称')
            ->assertJsonPath('sort', 20);

        $this->assertDatabaseHas('app_releases', [
            'id' => $app->id,
            'name' => '新名称',
        ]);
    }

    #[Test]
    #[TestDox('更新应用时 slug 不能与其它应用重复返回 422')]
    public function update_app_slug_cannot_duplicate(): void
    {
        $app1 = AppRelease::factory()->create(['slug' => 'app-one']);
        $app2 = AppRelease::factory()->create(['slug' => 'app-two']);

        $this->actingAsAdmin()->putJson("/admin/apps/{$app2->id}", [
            'name' => $app2->name,
            'slug' => 'app-one',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    #[TestDox('删除应用返回 204')]
    public function admin_can_delete_app(): void
    {
        $app = AppRelease::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/apps/{$app->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('app_releases', ['id' => $app->id]);
    }
}
