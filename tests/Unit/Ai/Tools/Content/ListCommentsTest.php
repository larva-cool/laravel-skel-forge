<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Content;

use App\Ai\Tools\Content\ListComments;
use App\Enums\AdminStatus;
use App\Enums\ReviewStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Content\Comment;
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
 * 评论列表查询工具测试
 */
#[CoversClass(ListComments::class)]
#[Group('ai')]
#[Group('comment')]
class ListCommentsTest extends TestCase
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
     * 创建一条评论
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createComment(array $overrides = []): Comment
    {
        return Comment::factory()->create(array_merge([
            'user_id' => User::factory(),
            'source_type' => 'comment',
            'source_id' => 1,
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListComments)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 comments.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListComments)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页评论数据')]
    public function handle_returns_paginated_comments(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.index']);
        $user = User::factory()->create();
        $this->createComment(['user_id' => $user->id]);
        $this->createComment(['user_id' => $user->id]);

        $result = (new ListComments)->forAdmin($admin)->handle(new Request(['user_id' => $user->id]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
        $this->assertSame($user->id, $payload['data'][0]['user']['id']);
    }

    #[Test]
    #[TestDox('status 筛选仅返回对应审核状态评论')]
    public function handle_filters_by_status(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.index']);
        $user = User::factory()->create();
        $this->createComment(['user_id' => $user->id, 'status' => ReviewStatus::PENDING]);
        $this->createComment(['user_id' => $user->id]);

        $result = (new ListComments)->forAdmin($admin)->handle(new Request([
            'user_id' => $user->id,
            'status' => 'pending',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('pending', $payload['data'][0]['status']);
    }

    #[Test]
    #[TestDox('keyword 筛选匹配评论内容')]
    public function handle_filters_by_keyword(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.index']);
        $this->createComment(['content' => '这个活动太棒了']);
        $this->createComment(['content' => '界面有点卡顿']);

        $result = (new ListComments)->forAdmin($admin)->handle(new Request(['keyword' => '活动']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('这个活动太棒了', $payload['data'][0]['content']);
    }
}
