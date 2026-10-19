<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Apps;

use App\Ai\Tools\Apps\ListAppVersions;
use App\Enums\AdminStatus;
use App\Enums\AppPlatform;
use App\Enums\ReleaseStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\App\AppRelease;
use App\Models\App\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * App 版本查询工具测试
 */
#[CoversClass(ListAppVersions::class)]
#[Group('ai')]
#[Group('app-version')]
class ListAppVersionsTest extends TestCase
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
        $result = (new ListAppVersions)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 app-versions.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListAppVersions)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页版本数据')]
    public function handle_returns_paginated_versions(): void
    {
        $admin = $this->createAdminWithPermissions(['app-versions.index']);
        $app = AppRelease::factory()->create();
        AppVersion::factory()->create(['app_release_id' => $app->id]);

        $result = (new ListAppVersions)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(1, $payload['pagination']['total']);
        $this->assertArrayHasKey('platform_label', $payload['data'][0]);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('platform 与 status 组合筛选仅返回匹配版本')]
    public function handle_filters_by_platform_and_status(): void
    {
        $admin = $this->createAdminWithPermissions(['app-versions.index']);
        $app = AppRelease::factory()->create();
        AppVersion::factory()->platform(AppPlatform::IOS)->create([
            'app_release_id' => $app->id,
            'status' => ReleaseStatus::PUBLISHED,
            'version' => '9.9.9',
        ]);
        AppVersion::factory()->platform(AppPlatform::ANDROID)->create([
            'app_release_id' => $app->id,
            'status' => ReleaseStatus::DRAFT,
        ]);

        $result = (new ListAppVersions)->forAdmin($admin)->handle(new Request([
            'platform' => 'ios',
            'status' => 'published',
            'version' => '9.9',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('ios', $payload['data'][0]['platform']);
        $this->assertSame('published', $payload['data'][0]['status']);
    }
}
