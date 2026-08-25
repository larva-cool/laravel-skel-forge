<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Content;

use App\Models\Content\Collection;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 可收藏的测试目标模型
 *
 * @property int $id
 * @property int $collection_count
 */
class CollectableStub extends Model
{
    protected $table = 'collectable_stubs';

    protected $guarded = [];
}

/**
 * Collection 模型单元测试
 */
#[CoversClass(Collection::class)]
#[Group('models')]
#[Group('collection')]
class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['collectable_stub' => CollectableStub::class]);

        Schema::create('collectable_stubs', function ($table) {
            $table->id();
            $table->unsignedInteger('collection_count')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('collectable_stubs');
        parent::tearDown();
    }

    /**
     * 创建可被收藏的目标
     */
    private function createSource(): CollectableStub
    {
        return CollectableStub::create([]);
    }

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        $collection = Collection::create([
            'user_id' => $user->id,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'extra' => ['from' => 'app'],
        ]);

        $this->assertSame($user->id, $collection->user_id);
        $this->assertSame($source->id, $collection->source_id);
        $this->assertSame(['from' => 'app'], $collection->extra);
    }

    #[Test]
    #[TestDox('表名为 collections')]
    public function table_name_is_collections(): void
    {
        $this->assertSame('collections', (new Collection)->getTable());
    }

    #[Test]
    #[TestDox('source_type 使用 morph 别名存储')]
    public function source_type_is_stored_as_morph_alias(): void
    {
        $collection = Collection::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame('collectable_stub', $collection->source_type);
    }

    #[Test]
    #[TestDox('id 与 source_id 正确 cast 为 integer')]
    public function id_fields_are_cast_to_integer(): void
    {
        $collection = Collection::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $collection->refresh();

        $this->assertIsInt($collection->id);
        $this->assertIsInt($collection->user_id);
        $this->assertIsInt($collection->source_id);
    }

    #[Test]
    #[TestDox('extra 正确 cast 为 json')]
    public function extra_is_cast_to_json(): void
    {
        $collection = Collection::factory()
            ->forSource($this->createSource())
            ->withExtra(['ip' => '127.0.0.1'])
            ->create(['user_id' => User::factory()->create()->id]);

        $collection->refresh();

        $this->assertIsArray($collection->extra);
        $this->assertSame('127.0.0.1', $collection->extra['ip']);
    }

    #[Test]
    #[TestDox('created_at 与 updated_at 自动维护')]
    public function timestamps_are_auto_maintained(): void
    {
        $collection = Collection::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertInstanceOf(Carbon::class, $collection->created_at);
        $this->assertInstanceOf(Carbon::class, $collection->updated_at);
    }

    #[Test]
    #[TestDox('user_id 在序列化时被隐藏')]
    public function user_id_is_hidden_in_array(): void
    {
        $collection = Collection::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertArrayNotHasKey('user_id', $collection->toArray());
    }

    #[Test]
    #[TestDox('user 关系返回收藏用户')]
    public function user_relation_returns_collector(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->forSource($this->createSource())->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $collection->user);
        $this->assertSame($user->id, $collection->user->id);
    }

    #[Test]
    #[TestDox('user 关系在用户注销后返回默认实例')]
    public function user_relation_returns_default_when_missing(): void
    {
        $collection = Collection::factory()->forSource($this->createSource())->create(['user_id' => 999999]);

        $this->assertSame('账号已注销', $collection->user->name);
    }

    #[Test]
    #[TestDox('source 多态关系返回收藏目标')]
    public function source_relation_returns_target(): void
    {
        $source = $this->createSource();
        $collection = Collection::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertInstanceOf(CollectableStub::class, $collection->source);
        $this->assertSame($source->id, $collection->source->id);
    }

    #[Test]
    #[TestDox('创建收藏后目标 collection_count 自增')]
    public function collection_count_increments_on_created(): void
    {
        $source = $this->createSource();

        $this->assertSame(0, $source->fresh()->collection_count);

        Collection::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $source->fresh()->collection_count);
    }

    #[Test]
    #[TestDox('删除收藏后目标 collection_count 自减')]
    public function collection_count_decrements_on_deleted(): void
    {
        $source = $this->createSource();
        $collection = Collection::factory()->forSource($source)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $source->fresh()->collection_count);

        $collection->delete();

        $this->assertSame(0, $source->fresh()->collection_count);
    }

    #[Test]
    #[TestDox('多个用户收藏同一目标时 collection_count 累加')]
    public function collection_count_accumulates_for_multiple_users(): void
    {
        $source = $this->createSource();

        Collection::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);
        Collection::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);

        $this->assertSame(2, $source->fresh()->collection_count);
    }

    #[Test]
    #[TestDox('同一用户重复收藏同一目标违反唯一约束')]
    public function duplicate_collection_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource();

        Collection::factory()->forSource($source)->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        Collection::factory()->forSource($source)->create(['user_id' => $user->id]);
    }

    #[Test]
    #[TestDox('forUser 作用域可按用户过滤收藏记录')]
    public function for_user_scope_filters_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Collection::factory()->forSource($this->createSource())->create(['user_id' => $user->id]);
        Collection::factory()->forSource($this->createSource())->create(['user_id' => $other->id]);

        $this->assertSame(1, Collection::forUser($user)->count());
        $this->assertSame(1, Collection::forUser($other->id)->count());
    }
}
