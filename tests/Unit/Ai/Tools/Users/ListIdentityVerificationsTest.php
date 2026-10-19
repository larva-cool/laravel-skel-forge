<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Users;

use App\Ai\Tools\Users\ListIdentityVerifications;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\User\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 实名认证记录查询工具测试
 */
#[CoversClass(ListIdentityVerifications::class)]
#[Group('ai')]
#[Group('identity')]
class ListIdentityVerificationsTest extends TestCase
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
        $result = (new ListIdentityVerifications)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 identity-verifications.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListIdentityVerifications)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页认证记录，且不包含证件号字段')]
    public function handle_returns_paginated_verifications(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.index']);
        IdentityVerification::factory()->personal()->create();
        IdentityVerification::factory()->enterprise()->create();

        $result = (new ListIdentityVerifications)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('type_label', $payload['data'][0]);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
        $this->assertArrayNotHasKey('id_card_no', $payload['data'][0]);
        $this->assertArrayNotHasKey('legal_person_id_card_no', $payload['data'][0]);
        $this->assertArrayNotHasKey('bank_account', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('type 与 status 组合筛选仅返回匹配记录')]
    public function handle_filters_by_type_and_status(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.index']);
        IdentityVerification::factory()->personal()->pending()->create();
        IdentityVerification::factory()->enterprise()->approved()->create();

        $result = (new ListIdentityVerifications)->forAdmin($admin)->handle(new Request([
            'type' => 'enterprise',
            'status' => 'approved',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('enterprise', $payload['data'][0]['type']);
        $this->assertSame('approved', $payload['data'][0]['status']);
    }

    #[Test]
    #[TestDox('keyword 筛选匹配企业名称')]
    public function handle_filters_by_keyword(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.index']);
        IdentityVerification::factory()->enterprise()->create(['company_name' => '星尘科技有限公司']);
        IdentityVerification::factory()->personal()->create(['real_name' => '李四']);

        $result = (new ListIdentityVerifications)->forAdmin($admin)->handle(new Request(['keyword' => '星尘']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('星尘科技有限公司', $payload['data'][0]['company_name']);
    }

    #[Test]
    #[TestDox('verifiable_id 筛选仅返回该用户的认证记录')]
    public function handle_filters_by_verifiable_id(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.index']);
        $user = User::factory()->create();
        IdentityVerification::factory()->create([
            'verifiable_type' => 'user',
            'verifiable_id' => $user->id,
        ]);
        IdentityVerification::factory()->create();

        $result = (new ListIdentityVerifications)->forAdmin($admin)->handle(new Request([
            'verifiable_id' => $user->id,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($user->id, $payload['data'][0]['user']['id']);
    }
}
