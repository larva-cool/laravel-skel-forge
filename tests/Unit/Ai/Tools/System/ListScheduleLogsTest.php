<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\ListScheduleLogs;
use App\Enums\AdminStatus;
use App\Enums\ScheduleStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\ScheduleLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 定时任务执行日志查询工具测试
 */
#[CoversClass(ListScheduleLogs::class)]
#[Group('ai')]
#[Group('schedule')]
class ListScheduleLogsTest extends TestCase
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
        $result = (new ListScheduleLogs)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 schedules.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListScheduleLogs)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页执行日志')]
    public function handle_returns_paginated_logs(): void
    {
        $admin = $this->createAdminWithPermissions(['schedules.index']);
        ScheduleLog::factory()->create(['name' => '单元测试任务日志']);

        $result = (new ListScheduleLogs)->forAdmin($admin)->handle(new Request(['name' => '单元测试任务日志']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
        $this->assertArrayHasKey('started_at', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('status 筛选仅返回失败日志')]
    public function handle_filters_by_status(): void
    {
        $admin = $this->createAdminWithPermissions(['schedules.index']);
        ScheduleLog::factory()->create(['name' => '单元测试日志', 'status' => ScheduleStatus::SUCCESS]);
        ScheduleLog::factory()->create(['name' => '单元测试日志', 'status' => ScheduleStatus::FAILED]);

        $result = (new ListScheduleLogs)->forAdmin($admin)->handle(new Request([
            'name' => '单元测试日志',
            'status' => 2,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame(2, $payload['data'][0]['status']);
    }
}
