<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Content;

use App\Models\Content\Follow;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Follow 模型的测试目标模型
 *
 * @property int $id
 * @property int $follow_count
 */
class FollowableStub extends Model
{
    use \App\Models\Traits\HasFollowable;

    protected $table = 'followable_stubs';

    protected $guarded = [];
}

/**
 * Follow 模型单元测试
 */
#[CoversClass(Follow::class)]
#[Group('models')]
#[Group('follow')]
class FollowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['followable_stub' => \Tests\Unit\Models\Content\FollowableStub::class]);

        Schema::create('followable_stubs', function ($table) {
            $table->id();
            $table->unsignedInteger('follow_count')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('followable_stubs');
        parent::tearDown();
    }

    /**
     * 创建可被关注的资源
     */
    private function createSource(): \Tests\Unit\Models\Content\FollowableStub
    {
        return \Tests\Unit\Models\Content\FollowableStub::create([]);
    }

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        $follow = Follow::create([
            'user_id' => $user->id,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'extra' => ['from' => 'app'],
        ]);

        $this->assertSame($user->id, $follow->user_id);
        $this->assertSame($source->id, $follow->source_id);
        $this->assertSame(['from' => 'app'], $follow->extra);
    }

    #[Test]
    #[TestDox('表名为 follows')]
    public function table_name_is_follows(): void
    {
        $this->assertSame('follows', (new Follow)->getTable());
    }

    #[Test]
    #[TestDox('source_type 使用 morph 别名存储')]
    public function source_type_is_stored_as_morph_alias(): void
    {
        $follow = Follow::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame('followable_stub', $follow->source_type);
    }

    #[Test]
    #[TestDox('id 与 source_id 正确 cast 为 integer')]
    public function id_fields_are_cast_to_integer(): void
    {
        $follow = Follow::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $follow->refresh();

        $this->assertIsInt($follow->id);
        $this->assertIsInt($follow->user_id);
        $this->assertIsInt($follow->source_id);
    }

    #[Test]
    #[TestDox('extra 正确 cast 为 json')]
    public function extra_is_cast_to_json(): void
    {
        $follow = Follow::factory()
            ->forSource($this->createSource())
            ->withExtra(['ip' => '127.0.0.1'])
            ->create(['user_id' => User::factory()->create()->id]);

        $follow->refresh();

        $this->assertIsArray($follow->extra);
        $this->assertSame('127.0.0.1', $follow->extra['ip']);
    }

    #[Test]
    #[TestDox('created_at 与 updated_at 自动维护')]
    public function timestamps_are_auto_maintained(): void
    {
        $follow = Follow::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $follow->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $follow->updated_at);
    }

    #[Test]
    #[TestDox('user 关系返回关注用户')]
    public function user_relation_returns_follower(): void
    {
        $user = User::factory()->create();
        $follow = Follow::factory()->forSource($this->createSource())->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $follow->user);
        $this->assertSame($user->id, $follow->user->id);
    }

    #[Test]
    #[TestDox('user 关系在用户注销后返回默认实例')]
    public function user_relation_returns_default_when_missing(): void
    {
        $follow = Follow::factory()->forSource($this->createSource())->create(['user_id' => 999999]);

        $this->assertSame('******', $follow->user->name);
    }

    #[Test]
    #[TestDox('source 多态关系返回关注目标')]
    public function source_relation_returns_target(): void
    {
        $source = $this->createSource();
        $follow = Follow::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertInstanceOf(FollowableStub::class, $follow->source);
        $this->assertSame($source->id, $follow->source->id);
    }

    #[Test]
    #[TestDox('创建关注后目标 follow_count 自增')]
    public function follow_count_increments_on_created(): void
    {
        $source = $this->createSource();

        $this->assertSame(0, $source->fresh()->follow_count);

        Follow::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $source->fresh()->follow_count);
    }

    #[Test]
    #[TestDox('删除关注后目标 follow_count 自减')]
    public function follow_count_decrements_on_deleted(): void
    {
        $source = $this->createSource();
        $follow = Follow::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $source->fresh()->follow_count);

        $follow->delete();

        $this->assertSame(0, $source->fresh()->follow_count);
    }

    #[Test]
    #[TestDox('多个用户关注同一目标时 follow_count 累加')]
    public function follow_count_accumulates_for_multiple_users(): void
    {
        $source = $this->createSource();

        Follow::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);
        Follow::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);

        $this->assertSame(2, $source->fresh()->follow_count);
    }

    #[Test]
    #[TestDox('同一用户重复关注同一目标违反唯一约束')]
    public function duplicate_follow_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        Follow::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        Follow::factory()->forSource($source)->create(['user_id' => $user->id]);
    }

    #[Test]
    #[TestDox('forUser 作用域可按用户过滤关注记录')]
    public function for_user_scope_filters_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Follow::factory()->forSource($this->createSource())->create(['user_id' => $user->id]);
        Follow::factory()->forSource($this->createSource())->create(['user_id' => $other->id]);

        $this->assertSame(1, Follow::forUser($user)->count());
        $this->assertSame(1, Follow::forUser($other->id)->count());
    }

    #[Test]
    #[TestDox('isFollowedBy 判断目标是否被指定用户关注')]
    public function is_followed_by_checks_user(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        $this->assertFalse($source->isFollowedBy($user));

        Follow::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->assertTrue($source->isFollowedBy($user));
        $this->assertTrue($source->isFollowedBy($user->id));
    }
}
