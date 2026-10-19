<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Content;

use App\Ai\Tools\Content\ModerateComment;
use App\Enums\AdminStatus;
use App\Enums\ReviewStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Content\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 评论审核工具测试
 */
#[CoversClass(ModerateComment::class)]
#[Group('ai')]
#[Group('comment')]
class ModerateCommentTest extends TestCase
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
            'source_type' => 'comment',
            'source_id' => 1,
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ModerateComment)->handle(new Request(['id' => 1, 'status' => 'approved']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 comments.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.index']);
        $comment = $this->createComment(['status' => ReviewStatus::PENDING]);

        $result = (new ModerateComment)->forAdmin($admin)->handle(new Request([
            'id' => $comment->id,
            'status' => 'approved',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(ReviewStatus::PENDING, $comment->fresh()->status);
    }

    #[Test]
    #[TestDox('审核通过后评论状态变更为已通过')]
    public function handle_approves_comment(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.edit']);
        $comment = $this->createComment(['status' => ReviewStatus::PENDING]);

        $result = (new ModerateComment)->forAdmin($admin)->handle(new Request([
            'id' => $comment->id,
            'status' => 'approved',
        ]));

        $this->assertSame(ReviewStatus::APPROVED, $comment->fresh()->status);
        $this->assertStringContainsString('已通过', (string) $result);
    }

    #[Test]
    #[TestDox('审核驳回后评论状态变更为已驳回')]
    public function handle_rejects_comment(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.edit']);
        $comment = $this->createComment();

        $result = (new ModerateComment)->forAdmin($admin)->handle(new Request([
            'id' => $comment->id,
            'status' => 'rejected',
        ]));

        $this->assertSame(ReviewStatus::REJECTED, $comment->fresh()->status);
        $this->assertStringContainsString('已驳回', (string) $result);
    }

    #[Test]
    #[TestDox('目标状态与当前一致时提示无需变更')]
    public function handle_skips_when_status_unchanged(): void
    {
        $admin = $this->createAdminWithPermissions(['comments.edit']);
        $comment = $this->createComment();

        $result = (new ModerateComment)->forAdmin($admin)->handle(new Request([
            'id' => $comment->id,
            'status' => 'approved',
        ]));

        $this->assertStringContainsString('无需变更', (string) $result);
    }
}
