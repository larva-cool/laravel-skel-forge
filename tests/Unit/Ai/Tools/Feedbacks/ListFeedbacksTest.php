<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Feedbacks;

use App\Ai\Tools\Feedbacks\ListFeedbacks;
use App\Enums\AdminStatus;
use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户反馈查询工具测试
 */
#[CoversClass(ListFeedbacks::class)]
#[Group('ai')]
#[Group('feedback')]
class ListFeedbacksTest extends TestCase
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
     * 创建一条反馈
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createFeedback(array $overrides = []): Feedback
    {
        return Feedback::query()->create(array_merge([
            'title' => '无法登录',
            'content' => '输入正确密码后提示账号异常',
            'type' => 'bug',
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListFeedbacks)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 feedbacks.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListFeedbacks)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页反馈数据')]
    public function handle_returns_paginated_feedbacks(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.index']);
        $this->createFeedback();
        $this->createFeedback(['title' => '建议增加深色模式', 'type' => 'suggestion']);

        $result = (new ListFeedbacks)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('code', $payload['data'][0]);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
        $this->assertArrayHasKey('priority_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('status 与 priority 组合筛选仅返回匹配反馈')]
    public function handle_filters_by_status_and_priority(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.index']);
        $this->createFeedback([
            'status' => FeedbackStatus::PENDING,
            'priority' => FeedbackPriority::URGENT,
        ]);
        $this->createFeedback([
            'title' => '已解决的建议',
            'status' => FeedbackStatus::RESOLVED,
            'priority' => FeedbackPriority::LOW,
        ]);

        $result = (new ListFeedbacks)->forAdmin($admin)->handle(new Request([
            'status' => 0,
            'priority' => 4,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame(0, $payload['data'][0]['status']);
        $this->assertSame(4, $payload['data'][0]['priority']);
        $this->assertSame('紧急', $payload['data'][0]['priority_label']);
    }

    #[Test]
    #[TestDox('keyword 筛选匹配标题或编号')]
    public function handle_filters_by_keyword(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.index']);
        $this->createFeedback(['title' => '充值未到账']);
        $this->createFeedback(['title' => '界面显示错乱']);

        $result = (new ListFeedbacks)->forAdmin($admin)->handle(new Request(['keyword' => '充值']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('充值未到账', $payload['data'][0]['title']);
    }
}
