<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Admin\CommentController;
use App\Models\Admin\Admin;
use App\Models\Content\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台评论管理控制器功能测试
 */
#[CoversClass(CommentController::class)]
#[Group('admin')]
#[Group('comment')]
class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
        $this->user = User::factory()->create();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条评论（挂在用户上作为多态目标）
     */
    private function createComment(array $attributes = []): Comment
    {
        $source = User::factory()->create();

        return Comment::factory()->forSource($source)->create(array_merge([
            'user_id' => $this->user->id,
        ], $attributes));
    }

    /**
     * 创建一条针对指定评论的回复
     */
    private function createReply(Comment $parent, array $attributes = []): Comment
    {
        return Comment::factory()->forSource($parent)->create(array_merge([
            'user_id' => $this->user->id,
        ], $attributes));
    }

    #[Test]
    #[TestDox('未登录访问评论列表返回 401')]
    public function guest_cannot_list_comments(): void
    {
        $this->getJson('/admin/comments')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取评论列表分页数据')]
    public function admin_can_list_comments(): void
    {
        $this->createComment();
        $this->createComment();
        $this->createComment();

        $response = $this->actingAsAdmin()->getJson('/admin/comments');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('列表返回数据包含状态标签和用户名')]
    public function list_response_contains_label_fields(): void
    {
        $this->createComment(['status' => ReviewStatus::PENDING]);

        $response = $this->actingAsAdmin()->getJson('/admin/comments');

        $data = $response->json('data.0');
        $this->assertArrayHasKey('status_label', $data);
        $this->assertArrayHasKey('status_label_type', $data);
        $this->assertArrayHasKey('username', $data);
    }

    #[Test]
    #[TestDox('按状态筛选评论')]
    public function admin_can_filter_by_status(): void
    {
        $this->createComment(['status' => ReviewStatus::PENDING]);
        $this->createComment(['status' => ReviewStatus::APPROVED]);
        $this->createComment(['status' => ReviewStatus::REJECTED]);

        $response = $this->actingAsAdmin()->getJson('/admin/comments?status=pending');

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按关键词搜索评论内容')]
    public function admin_can_search_by_keyword(): void
    {
        $this->createComment(['content' => '这条评论内容很特别']);
        $this->createComment(['content' => '普通评论']);

        $response = $this->actingAsAdmin()->getJson('/admin/comments?keyword='.rawurlencode('特别'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按源类型筛选评论')]
    public function admin_can_filter_by_source_type(): void
    {
        $this->createComment(['source_type' => 'user']);
        $this->createComment(['source_type' => 'user']);

        $response = $this->actingAsAdmin()->getJson('/admin/comments?source_type=user');

        $response->assertOk()->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('管理员可查看评论详情')]
    public function admin_can_view_comment_detail(): void
    {
        $comment = $this->createComment(['content' => '详细评论内容', 'ip_address' => '127.0.0.1']);

        $response = $this->actingAsAdmin()->getJson("/admin/comments/{$comment->id}");

        $response->assertOk()
            ->assertJsonPath('id', $comment->id)
            ->assertJsonPath('content', '详细评论内容')
            ->assertJsonPath('ip_address', '127.0.0.1');
    }

    #[Test]
    #[TestDox('管理员审核通过评论')]
    public function admin_can_approve_comment(): void
    {
        $comment = $this->createComment(['status' => ReviewStatus::PENDING]);

        $response = $this->actingAsAdmin()->postJson("/admin/comments/{$comment->id}/review", [
            'status' => ReviewStatus::APPROVED->value,
        ]);

        $response->assertOk();
        $comment->refresh();
        $this->assertTrue($comment->status->isApproved());
    }

    #[Test]
    #[TestDox('管理员驳回评论')]
    public function admin_can_reject_comment(): void
    {
        $comment = $this->createComment(['status' => ReviewStatus::PENDING]);

        $response = $this->actingAsAdmin()->postJson("/admin/comments/{$comment->id}/review", [
            'status' => ReviewStatus::REJECTED->value,
            'remark' => '内容违规',
        ]);

        $response->assertOk();
        $comment->refresh();
        $this->assertTrue($comment->status->isRejected());
    }

    #[Test]
    #[TestDox('审核时无效状态返回 422')]
    public function invalid_status_returns_validation_error(): void
    {
        $comment = $this->createComment();

        $response = $this->actingAsAdmin()->postJson("/admin/comments/{$comment->id}/review", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('审核时状态字段必填')]
    public function review_requires_status(): void
    {
        $comment = $this->createComment();

        $response = $this->actingAsAdmin()->postJson("/admin/comments/{$comment->id}/review", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    #[TestDox('管理员可删除评论')]
    public function admin_can_delete_comment(): void
    {
        $comment = $this->createComment();

        $this->actingAsAdmin()->deleteJson("/admin/comments/{$comment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    #[Test]
    #[TestDox('普通评论详情中 is_reply 为 false 且不含上级评论')]
    public function comment_detail_marks_non_reply_correctly(): void
    {
        $comment = $this->createComment();

        $response = $this->actingAsAdmin()->getJson("/admin/comments/{$comment->id}");

        $response->assertOk()
            ->assertJsonPath('is_reply', false)
            ->assertJsonMissingPath('parent_comment');
    }

    #[Test]
    #[TestDox('回复评论详情返回上级评论摘要')]
    public function reply_detail_returns_parent_comment_summary(): void
    {
        $parent = $this->createComment(['content' => '这是父级评论']);
        $reply = $this->createReply($parent, ['content' => '这是回复内容']);

        $response = $this->actingAsAdmin()->getJson("/admin/comments/{$reply->id}");

        $response->assertOk()
            ->assertJsonPath('is_reply', true)
            ->assertJsonPath('parent_comment.id', $parent->id)
            ->assertJsonPath('parent_comment.content', '这是父级评论')
            ->assertJsonStructure([
                'parent_comment' => [
                    'id', 'user_id', 'username', 'content',
                    'status', 'status_label', 'status_label_type', 'created_at',
                ],
            ]);
    }

    #[Test]
    #[TestDox('未登录访问回复列表返回 401')]
    public function guest_cannot_list_replies(): void
    {
        $comment = $this->createComment();

        $this->getJson("/admin/comments/{$comment->id}/replies")->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取指定评论的回复列表')]
    public function admin_can_list_comment_replies(): void
    {
        $parent = $this->createComment();
        $this->createReply($parent);
        $this->createReply($parent);

        // 干扰数据：另一条评论下的回复不应出现
        $other = $this->createComment();
        $this->createReply($other);

        $response = $this->actingAsAdmin()->getJson("/admin/comments/{$parent->id}/replies");

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $item) {
            $this->assertSame('comment', $item['source_type']);
            $this->assertSame($parent->id, $item['source_id']);
        }
    }

    #[Test]
    #[TestDox('评论无回复时回复列表返回空数组')]
    public function replies_list_is_empty_when_no_replies(): void
    {
        $comment = $this->createComment();

        $this->actingAsAdmin()->getJson("/admin/comments/{$comment->id}/replies")
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    #[TestDox('获取不存在评论的回复列表返回 404')]
    public function replies_list_returns_404_for_missing_comment(): void
    {
        $this->actingAsAdmin()->getJson('/admin/comments/999999/replies')
            ->assertNotFound();
    }
}
