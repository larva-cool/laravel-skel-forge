<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Audit;

use App\Ai\Tools\Audit\ListAuditLogs;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 后台操作日志查询工具测试
 */
#[CoversClass(ListAuditLogs::class)]
#[Group('ai')]
#[Group('audit')]
class ListAuditLogsTest extends TestCase
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
        $result = (new ListAuditLogs)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 audit-logs.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListAuditLogs)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页日志数据')]
    public function handle_returns_paginated_logs(): void
    {
        $admin = $this->createAdminWithPermissions(['audit-logs.index']);
        AuditLog::factory()->count(3)->create();

        $result = (new ListAuditLogs)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('pagination', $payload);
        $this->assertSame(3, $payload['pagination']['total']);
        $this->assertArrayHasKey('is_failed', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('is_failed 筛选仅返回失败操作日志')]
    public function handle_filters_failed_logs(): void
    {
        $admin = $this->createAdminWithPermissions(['audit-logs.index']);
        AuditLog::factory()->create(['status_code' => 200]);
        AuditLog::factory()->failed()->create();

        $result = (new ListAuditLogs)->forAdmin($admin)->handle(new Request(['is_failed' => true]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertTrue($payload['data'][0]['is_failed']);
        $this->assertSame(422, $payload['data'][0]['status_code']);
    }

    #[Test]
    #[TestDox('支持按操作人与请求方法组合筛选')]
    public function handle_filters_by_operator_and_method(): void
    {
        $admin = $this->createAdminWithPermissions(['audit-logs.index']);
        AuditLog::factory()->create(['operator_name' => 'zhangsan', 'method' => 'PUT']);
        AuditLog::factory()->create(['operator_name' => 'lisi', 'method' => 'DELETE']);

        $result = (new ListAuditLogs)->forAdmin($admin)->handle(new Request([
            'operator_name' => 'zhang',
            'method' => 'PUT',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('zhangsan', $payload['data'][0]['operator_name']);
        $this->assertSame('PUT', $payload['data'][0]['method']);
    }
}
