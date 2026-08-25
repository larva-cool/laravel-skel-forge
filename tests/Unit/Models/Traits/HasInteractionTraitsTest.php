<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use App\Models\Content\Collection;
use App\Models\Content\Comment;
use App\Models\Content\Like;
use App\Models\Model;
use App\Models\Traits\HasCollectionable;
use App\Models\Traits\HasCommentable;
use App\Models\Traits\HasLikeable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 同时支持点赞、收藏、评论的测试目标模型
 *
 * @property int $id
 * @property int $like_count
 * @property int $collection_count
 */
class InteractableStub extends Model
{
    use HasCollectionable, HasCommentable, HasLikeable;

    protected $table = 'interactable_stubs';

    protected $guarded = [];
}

/**
 * 互动关系 trait 单元测试
 */
#[CoversClass(HasLikeable::class)]
#[CoversClass(HasCollectionable::class)]
#[CoversClass(HasCommentable::class)]
#[Group('traits')]
class HasInteractionTraitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['interactable_stub' => InteractableStub::class]);

        Schema::create('interactable_stubs', function ($table) {
            $table->id();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('collection_count')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('interactable_stubs');
        parent::tearDown();
    }

    /**
     * 创建可互动的目标
     */
    private function createSource(): InteractableStub
    {
        return InteractableStub::create([]);
    }

    #[Test]
    #[TestDox('likes 关系返回目标的点赞列表')]
    public function likes_relation_returns_likes(): void
    {
        $source = $this->createSource();

        Like::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);
        Like::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);

        $this->assertCount(2, $source->likes);
        $this->assertInstanceOf(Like::class, $source->likes->first());
    }

    #[Test]
    #[TestDox('isLikedBy 传入用户实例可判断是否已点赞')]
    public function is_liked_by_accepts_user_instance(): void
    {
        $source = $this->createSource();
        $user = User::factory()->create();

        $this->assertFalse($source->isLikedBy($user));

        Like::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->assertTrue($source->isLikedBy($user));
    }

    #[Test]
    #[TestDox('isLikedBy 传入用户ID可判断是否已点赞')]
    public function is_liked_by_accepts_user_id(): void
    {
        $source = $this->createSource();
        $user = User::factory()->create();

        Like::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->assertTrue($source->isLikedBy($user->id));
        $this->assertTrue($source->isLikedBy((string) $user->id));
        $this->assertFalse($source->isLikedBy(999999));
    }

    #[Test]
    #[TestDox('collections 关系返回目标的收藏列表')]
    public function collections_relation_returns_collections(): void
    {
        $source = $this->createSource();

        Collection::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertCount(1, $source->collections);
        $this->assertInstanceOf(Collection::class, $source->collections->first());
    }

    #[Test]
    #[TestDox('isCollected 传入用户实例可判断是否已收藏')]
    public function is_collected_accepts_user_instance(): void
    {
        $source = $this->createSource();
        $user = User::factory()->create();

        $this->assertFalse($source->isCollected($user));

        Collection::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->assertTrue($source->isCollected($user));
    }

    #[Test]
    #[TestDox('isCollected 传入用户ID可判断是否已收藏')]
    public function is_collected_accepts_user_id(): void
    {
        $source = $this->createSource();
        $user = User::factory()->create();

        Collection::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->assertTrue($source->isCollected($user->id));
        $this->assertTrue($source->isCollected((string) $user->id));
        $this->assertFalse($source->isCollected(999999));
    }

    #[Test]
    #[TestDox('comments 关系返回目标的评论列表')]
    public function comments_relation_returns_comments(): void
    {
        $source = $this->createSource();

        Comment::factory()->forSource($source)->count(3)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertCount(3, $source->comments);
        $this->assertInstanceOf(Comment::class, $source->comments->first());
    }

    #[Test]
    #[TestDox('三种互动关系互不干扰')]
    public function interactions_are_isolated(): void
    {
        $source = $this->createSource();
        $other = $this->createSource();
        $user = User::factory()->create();

        Like::factory()->forSource($source)->create(['user_id' => $user->id]);
        Collection::factory()->forSource($source)->create(['user_id' => $user->id]);
        Comment::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->assertCount(1, $source->likes);
        $this->assertCount(1, $source->collections);
        $this->assertCount(1, $source->comments);

        $this->assertCount(0, $other->likes);
        $this->assertCount(0, $other->collections);
        $this->assertCount(0, $other->comments);
    }
}
