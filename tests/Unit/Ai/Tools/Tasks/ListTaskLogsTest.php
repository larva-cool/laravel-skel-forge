<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Tasks;

use App\Ai\Tools\Tasks\ListTaskLogs;
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
use App\Models\Task\TaskLog;
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
 * 任务奖励发放日志查询工具测试
 */
#[CoversClass(ListTaskLogs::class)]
#[Group('ai')]
#[Group('task')]
class ListTaskLogsTest extends TestCase
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
     * 创建一条任务奖励日志
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createLog(User $user, array $overrides = []): TaskLog
    {
        $group = TaskGroup::query()->create([
            'name' => '单元测试分组',
            'code' => 'group_'.uniqid(),
            'type' => TaskGroupType::DAILY,
            'reset_type' => TaskResetType::DAILY,
            'sort_order' => 0,
            'status' => true,
        ]);

        $task = Task::query()->create([
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
        ]);

        return TaskLog::query()->create(array_merge([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'period_key' => now()->format('Y-m-d'),
            'reward_type' => TaskRewardType::COIN,
            'reward_amount' => 10,
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListTaskLogs)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 task-logs.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListTaskLogs)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页日志数据')]
    public function handle_returns_paginated_logs(): void
    {
        $admin = $this->createAdminWithPermissions(['task-logs.index']);
        $user = User::factory()->create();
        $this->createLog($user);
        $this->createLog($user);

        $result = (new ListTaskLogs)->forAdmin($admin)->handle(new Request(['user_id' => $user->id]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('reward_type_label', $payload['data'][0]);
        $this->assertSame($user->id, $payload['data'][0]['user']['id']);
        $this->assertSame('每日签到', $payload['data'][0]['task']['name']);
    }

    #[Test]
    #[TestDox('user_id 与 reward_type 组合筛选仅返回匹配日志')]
    public function handle_filters_by_user_and_reward_type(): void
    {
        $admin = $this->createAdminWithPermissions(['task-logs.index']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createLog($userA, ['reward_type' => TaskRewardType::POINT, 'reward_amount' => 5]);
        $this->createLog($userB);

        $result = (new ListTaskLogs)->forAdmin($admin)->handle(new Request([
            'user_id' => $userA->id,
            'reward_type' => 'point',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($userA->id, $payload['data'][0]['user']['id']);
        $this->assertSame('point', $payload['data'][0]['reward_type']);
    }
}
