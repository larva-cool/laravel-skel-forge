<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Open;

use App\Ai\Tools\Open\ListOpenAccounts;
use App\Enums\AdminStatus;
use App\Enums\IdentityStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Open\OpenAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 开放平台账户列表查询工具测试
 */
#[CoversClass(ListOpenAccounts::class)]
#[Group('ai')]
#[Group('open-account')]
class ListOpenAccountsTest extends TestCase
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
        $result = (new ListOpenAccounts)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 open_accounts.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListOpenAccounts)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页账户摘要')]
    public function handle_returns_paginated_accounts(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.index']);
        OpenAccount::factory()->create(['name' => '单元测试合作方']);

        $result = (new ListOpenAccounts)->forAdmin($admin)->handle(new Request(['keyword' => '单元测试合作方']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
        $this->assertArrayHasKey('identity_status_label', $payload['data'][0]);
        $this->assertArrayHasKey('tokens_count', $payload['data'][0]);
        $this->assertArrayNotHasKey('password', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('identity_status 筛选仅返回对应实名状态账户')]
    public function handle_filters_by_identity_status(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.index']);
        OpenAccount::factory()->create([
            'name' => '单元测试已认证',
            'identity_status' => IdentityStatus::APPROVED,
        ]);
        OpenAccount::factory()->create([
            'name' => '单元测试未认证',
            'identity_status' => IdentityStatus::UNSUBMITTED,
        ]);

        $result = (new ListOpenAccounts)->forAdmin($admin)->handle(new Request([
            'keyword' => '单元测试',
            'identity_status' => 'approved',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('approved', $payload['data'][0]['identity_status']);
    }
}
