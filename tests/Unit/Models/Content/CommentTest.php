<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Content;

use App\Enums\ReviewStatus;
use App\Models\Content\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Comment 模型单元测试
 */
#[CoversClass(Comment::class)]
#[Group('models')]
#[Group('comment')]
class CommentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $user = User::factory()->create();

        $comment = Comment::create([
            'user_id' => $user->id,
            'source_type' => $user->getMorphClass(),
            'source_id' => $user->id,
            'is_top' => true,
            'status' => ReviewStatus::APPROVED,
            'content' => '这是一条评论',
            'ip_address' => '127.0.0.1',
            'mentioned_users' => [1, 2],
        ]);

        $this->assertSame($user->id, $comment->user_id);
        $this->assertSame('这是一条评论', $comment->content);
        $this->assertTrue($comment->is_top);
        $this->assertSame([1, 2], $comment->mentioned_users);
    }

    #[Test]
    #[TestDox('表名为 comments')]
    public function table_name_is_comments(): void
    {
        $this->assertSame('comments', (new Comment)->getTable());
    }

    #[Test]
    #[TestDox('UPDATED_AT 为 null 不维护更新时间戳')]
    public function updated_at_is_null_constant(): void
    {
        $this->assertNull(Comment::UPDATED_AT);
    }

    #[Test]
    #[TestDox('创建评论不写入 updated_at 字段')]
    public function created_comment_has_no_updated_at(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);

        $this->assertNotNull($comment->created_at);
        $this->assertArrayNotHasKey('updated_at', $comment->getAttributes());
    }

    #[Test]
    #[TestDox('source_type 使用 morph 别名存储')]
    public function source_type_is_stored_as_morph_alias(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);

        $this->assertSame('user', $comment->source_type);
    }

    #[Test]
    #[TestDox('计数与ID字段正确 cast 为 integer')]
    public function integer_fields_are_cast_to_integer(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create([
            'user_id' => $user->id,
            'like_count' => '5',
            'comment_count' => '3',
        ]);

        $comment->refresh();

        $this->assertIsInt($comment->id);
        $this->assertIsInt($comment->user_id);
        $this->assertIsInt($comment->source_id);
        $this->assertSame(5, $comment->like_count);
        $this->assertSame(3, $comment->comment_count);
    }

    #[Test]
    #[TestDox('is_top 正确 cast 为 boolean')]
    public function is_top_is_cast_to_boolean(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->top()->create(['user_id' => $user->id]);

        $comment->refresh();

        $this->assertTrue($comment->is_top);
    }

    #[Test]
    #[TestDox('mentioned_users 正确 cast 为 array')]
    public function mentioned_users_is_cast_to_array(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()
            ->forSource($user)
            ->withMentionedUsers([10, 20])
            ->create(['user_id' => $user->id]);

        $comment->refresh();

        $this->assertSame([10, 20], $comment->mentioned_users);
    }

    #[Test]
    #[TestDox('created_at 正确 cast 为 datetime')]
    public function created_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $comment->created_at);
    }

    #[Test]
    #[TestDox('status 正确 cast 为 ReviewStatus 枚举')]
    public function status_is_cast_to_review_status_enum(): void
    {
        $user = User::factory()->create();

        $approved = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);
        $this->assertInstanceOf(ReviewStatus::class, $approved->status);
        $this->assertTrue($approved->status->isApproved());

        $pending = Comment::factory()->forSource($user)->pending()->create(['user_id' => $user->id]);
        $this->assertTrue($pending->status->isPending());

        $review = Comment::factory()->forSource($user)->create([
            'user_id' => $user->id,
            'status' => ReviewStatus::REVIEW,
        ]);
        $this->assertTrue($review->status->isReview());

        $rejected = Comment::factory()->forSource($user)->create([
            'user_id' => $user->id,
            'status' => ReviewStatus::REJECTED,
        ]);
        $this->assertTrue($rejected->status->isRejected());
    }

    #[Test]
    #[TestDox('user 关系返回评论用户')]
    public function user_relation_returns_commenter(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $comment->user);
        $this->assertSame($user->id, $comment->user->id);
    }

    #[Test]
    #[TestDox('user 关系在用户注销后返回默认实例')]
    public function user_relation_returns_default_when_missing(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => 999999]);

        $this->assertSame('账号已注销', $comment->user->name);
    }

    #[Test]
    #[TestDox('source 多态关系返回评论目标')]
    public function source_relation_returns_target(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $comment->source);
        $this->assertSame($user->id, $comment->source->id);
    }

    #[Test]
    #[TestDox('comments 关系返回评论的回复列表')]
    public function comments_relation_returns_replies(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);

        Comment::factory()->forSource($comment)->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $comment->comments);
        $this->assertInstanceOf(Comment::class, $comment->comments->first());
    }

    #[Test]
    #[TestDox('回复评论的 source_type 为 comment 别名')]
    public function reply_source_type_is_comment_alias(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->forSource($user)->create(['user_id' => $user->id]);
        $reply = Comment::factory()->forSource($comment)->create(['user_id' => $user->id]);

        $this->assertSame('comment', $reply->source_type);
        $this->assertInstanceOf(Comment::class, $reply->source);
        $this->assertSame($comment->id, $reply->source->id);
    }

    #[Test]
    #[TestDox('forUser 作用域可按用户过滤评论记录')]
    public function for_user_scope_filters_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Comment::factory()->forSource($user)->create(['user_id' => $user->id]);
        Comment::factory()->forSource($user)->count(2)->create(['user_id' => $other->id]);

        $this->assertSame(1, Comment::forUser($user)->count());
        $this->assertSame(2, Comment::forUser($other->id)->count());
    }
}
