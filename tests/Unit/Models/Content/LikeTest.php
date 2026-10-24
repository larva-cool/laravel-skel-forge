<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Content;

use App\Models\Content\Comment;
use App\Models\Content\Like;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Like 模型单元测试
 */
#[CoversClass(Like::class)]
#[Group('models')]
#[Group('like')]
class LikeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建可被点赞的评论
     */
    private function createSource(): Comment
    {
        $user = User::factory()->create();

        return Comment::factory()->forSource($user)->create(['user_id' => $user->id]);
    }

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        $like = Like::create([
            'user_id' => $user->id,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'extra' => ['from' => 'app'],
        ]);

        $this->assertSame($user->id, $like->user_id);
        $this->assertSame($source->id, $like->source_id);
        $this->assertSame(['from' => 'app'], $like->extra);
    }

    #[Test]
    #[TestDox('表名为 likes')]
    public function table_name_is_likes(): void
    {
        $this->assertSame('likes', (new Like)->getTable());
    }

    #[Test]
    #[TestDox('source_type 使用 morph 别名存储')]
    public function source_type_is_stored_as_morph_alias(): void
    {
        $like = Like::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame('comment', $like->source_type);
    }

    #[Test]
    #[TestDox('id 与 source_id 正确 cast 为 integer')]
    public function id_fields_are_cast_to_integer(): void
    {
        $like = Like::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $like->refresh();

        $this->assertIsInt($like->id);
        $this->assertIsInt($like->user_id);
        $this->assertIsInt($like->source_id);
    }

    #[Test]
    #[TestDox('extra 正确 cast 为 json')]
    public function extra_is_cast_to_json(): void
    {
        $like = Like::factory()
            ->forSource($this->createSource())
            ->withExtra(['ip' => '127.0.0.1'])
            ->create(['user_id' => User::factory()->create()->id]);

        $like->refresh();

        $this->assertIsArray($like->extra);
        $this->assertSame('127.0.0.1', $like->extra['ip']);
    }

    #[Test]
    #[TestDox('created_at 与 updated_at 自动维护')]
    public function timestamps_are_auto_maintained(): void
    {
        $like = Like::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $like->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $like->updated_at);
    }

    #[Test]
    #[TestDox('user 关系返回点赞用户')]
    public function user_relation_returns_liker(): void
    {
        $user = User::factory()->create();
        $like = Like::factory()->forSource($this->createSource())->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $like->user);
        $this->assertSame($user->id, $like->user->id);
    }

    #[Test]
    #[TestDox('user 关系在用户注销后返回默认实例')]
    public function user_relation_returns_default_when_missing(): void
    {
        $like = Like::factory()->forSource($this->createSource())->create(['user_id' => 999999]);

        $this->assertSame('******', $like->user->name);
    }

    #[Test]
    #[TestDox('source 多态关系返回点赞目标')]
    public function source_relation_returns_target(): void
    {
        $source = $this->createSource();
        $like = Like::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertInstanceOf(Comment::class, $like->source);
        $this->assertSame($source->id, $like->source->id);
    }

    #[Test]
    #[TestDox('创建点赞后目标 like_count 自增')]
    public function like_count_increments_on_created(): void
    {
        $source = $this->createSource();

        $this->assertSame(0, $source->fresh()->like_count);

        Like::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $source->fresh()->like_count);
    }

    #[Test]
    #[TestDox('删除点赞后目标 like_count 自减')]
    public function like_count_decrements_on_deleted(): void
    {
        $source = $this->createSource();
        $like = Like::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $source->fresh()->like_count);

        $like->delete();

        $this->assertSame(0, $source->fresh()->like_count);
    }

    #[Test]
    #[TestDox('多个用户点赞同一目标时 like_count 累加')]
    public function like_count_accumulates_for_multiple_users(): void
    {
        $source = $this->createSource();

        Like::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);
        Like::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);

        $this->assertSame(2, $source->fresh()->like_count);
    }

    #[Test]
    #[TestDox('同一用户重复点赞同一目标违反唯一约束')]
    public function duplicate_like_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        Like::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        Like::factory()->forSource($source)->create(['user_id' => $user->id]);
    }

    #[Test]
    #[TestDox('forUser 作用域可按用户过滤点赞记录')]
    public function for_user_scope_filters_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Like::factory()->forSource($this->createSource())->create(['user_id' => $user->id]);
        Like::factory()->forSource($this->createSource())->create(['user_id' => $other->id]);

        $this->assertSame(1, Like::forUser($user)->count());
        $this->assertSame(1, Like::forUser($other->id)->count());
    }
}
