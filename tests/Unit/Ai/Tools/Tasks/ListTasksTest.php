<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Tasks;

use App\Ai\Tools\Tasks\ListTasks;
use App\Enums\AdminStatus;
use App\Enums\Task\TaskClaimMode;
use App\Enums\Task\TaskGroupType;
use App\Enums\Task\TaskResetType;
use App\Enums\Task\TaskRewardType;
use App\Enums\Task\TaskType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Task\Task;
use App\Models\Task\TaskGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 任务定义列表查询工具测试
 */
#[CoversClass(ListTasks::class)]
#[Group('ai')]
#[Group('task')]
class ListTasksTest extends TestCase
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
     * 创建一个任务分组
     */
    private function createGroup(): TaskGroup
    {
        return TaskGroup::query()->create([
            'name' => '日常任务',
            'code' => 'daily',
            'type' => TaskGroupType::DAILY,
            'reset_type' => TaskResetType::DAILY,
            'sort_order' => 0,
            'status' => true,
        ]);
    }

    /**
     * 创建一个任务定义
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createTask(TaskGroup $group, array $overrides = []): Task
    {
        return Task::query()->create(array_merge([
            'group_id' => $group->id,
            'name' => '每日签到',
            'code' => 'task_'.uniqid(),
            'type' => TaskType::SIGN_IN,
            'condition' => ['target_value' => 1],
            'reward_type' => TaskRewardType::COIN,
            'reward_amount' => 10,
            'claim_mode' => TaskClaimMode::AUTO,
            'sort_order' => 0,
            'status' => true,
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListTasks)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 tasks.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListTasks)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页任务数据')]
    public function handle_returns_paginated_tasks(): void
    {
        $admin = $this->createAdminWithPermissions(['tasks.index']);
        $group = $this->createGroup();
        $this->createTask($group, ['name' => '单元测试任务签到']);
        $this->createTask($group, ['name' => '单元测试任务资料', 'type' => TaskType::COMPLETE_PROFILE]);

        $result = (new ListTasks)->forAdmin($admin)->handle(new Request([
            'keyword' => '单元测试任务',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('type_label', $payload['data'][0]);
        $this->assertArrayHasKey('reward_type_label', $payload['data'][0]);
        $this->assertArrayHasKey('is_active', $payload['data'][0]);
        $this->assertSame('日常任务', $payload['data'][0]['group']['name']);
    }

    #[Test]
    #[TestDox('type 与 status 组合筛选仅返回匹配任务')]
    public function handle_filters_by_type_and_status(): void
    {
        $admin = $this->createAdminWithPermissions(['tasks.index']);
        $group = $this->createGroup();
        $this->createTask($group);
        $this->createTask($group, ['name' => '邀请好友', 'type' => TaskType::INVITE_USER, 'status' => false]);

        $result = (new ListTasks)->forAdmin($admin)->handle(new Request([
            'group_id' => $group->id,
            'type' => 'invite_user',
            'status' => false,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('invite_user', $payload['data'][0]['type']);
        $this->assertFalse($payload['data'][0]['status']);
    }
}
