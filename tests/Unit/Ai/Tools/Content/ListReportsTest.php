<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Content;

use App\Ai\Tools\Content\ListReports;
use App\Enums\AdminStatus;
use App\Enums\ReportStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Content\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 举报列表查询工具测试
 */
#[CoversClass(ListReports::class)]
#[Group('ai')]
#[Group('report')]
class ListReportsTest extends TestCase
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

    /**
     * 创建一条举报
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createReport(User $reporter, array $overrides = []): Report
    {
        return Report::query()->create(array_merge([
            'user_id' => $reporter->id,
            'reportable_id' => 1,
            'reportable_type' => 'comment',
            'category' => 'spam',
            'reason' => '垃圾广告信息',
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListReports)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 reports.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListReports)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页举报数据')]
    public function handle_returns_paginated_reports(): void
    {
        $admin = $this->createAdminWithPermissions(['reports.index']);
        $reporter = User::factory()->create();
        $this->createReport($reporter, ['reason' => '单元测试举报内容']);

        $result = (new ListReports)->forAdmin($admin)->handle(new Request(['keyword' => '单元测试举报']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($reporter->id, $payload['data'][0]['reporter']['id']);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('status 与 category 组合筛选仅返回匹配举报')]
    public function handle_filters_by_status_and_category(): void
    {
        $admin = $this->createAdminWithPermissions(['reports.index']);
        $reporter = User::factory()->create();
        $this->createReport($reporter, ['category' => 'spam']);
        $this->createReport($reporter, ['category' => 'abuse', 'reason' => '辱骂他人']);

        $result = (new ListReports)->forAdmin($admin)->handle(new Request([
            'status' => 'pending',
            'category' => 'abuse',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('abuse', $payload['data'][0]['category']);
        $this->assertSame('pending', $payload['data'][0]['status']);
    }
}
