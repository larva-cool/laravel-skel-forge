<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\Content\CollectionController;
use App\Models\Content\Collection;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 收藏接口的测试目标模型
 *
 * @property int $id
 * @property int $collection_count
 */
class ApiCollectableStub extends Model
{
    use \App\Models\Traits\HasCollectionable;

    protected $table = 'api_collectable_stubs';

    protected $guarded = [];
}

/**
 * 收藏接口功能测试
 */
#[CoversClass(CollectionController::class)]
#[Group('api')]
#[Group('collection')]
class CollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['api_collectable_stub' => ApiCollectableStub::class]);

        Schema::create('api_collectable_stubs', function ($table) {
            $table->id();
            $table->unsignedInteger('collection_count')->default(0);
            $table->timestamps();
        });

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('api_collectable_stubs');
        parent::tearDown();
    }

    /**
     * 以当前用户身份认证
     */
    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    /**
     * 创建可被收藏的目标
     */
    private function createSource(): ApiCollectableStub
    {
        return ApiCollectableStub::create([]);
    }

    #[Test]
    #[TestDox('登录用户可添加收藏')]
    public function authenticated_user_can_store_collection(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $this->postJson('/api/v1/collections', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'extra' => ['from' => 'app'],
        ])
            ->assertCreated()
            ->assertJsonPath('source_type', 'api_collectable_stub')
            ->assertJsonPath('source_id', $source->id)
            ->assertJsonPath('extra.from', 'app');

        $this->assertDatabaseHas('collections', [
            'user_id' => $this->user->id,
            'source_type' => 'api_collectable_stub',
            'source_id' => $source->id,
        ]);
        $this->assertSame(1, $source->fresh()->collection_count);
    }

    #[Test]
    #[TestDox('添加收藏缺少必填字段返回 422')]
    public function store_requires_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/collections', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type', 'source_id']);
    }

    #[Test]
    #[TestDox('source_type 不在 morph 映射内返回 422')]
    public function store_rejects_unknown_source_type(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/collections', [
            'source_type' => 'unknown_type',
            'source_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type']);
    }

    #[Test]
    #[TestDox('重复收藏同一资源返回 422')]
    public function store_rejects_duplicate_collection(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        Collection::factory()->forSource($source)->create(['user_id' => $this->user->id]);

        $this->postJson('/api/v1/collections', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_id']);
    }

    #[Test]
    #[TestDox('登录用户可获取自己的收藏列表')]
    public function authenticated_user_can_list_own_collections(): void
    {
        $this->actingAsUser();

        Collection::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Collection::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Collection::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->getJson('/api/v1/collections')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('收藏列表可按 source_type 过滤')]
    public function collection_list_can_filter_by_source_type(): void
    {
        $this->actingAsUser();

        Collection::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Collection::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);

        $this->getJson('/api/v1/collections?source_type=api_collectable_stub')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.source_type', 'api_collectable_stub');
    }

    #[Test]
    #[TestDox('收藏列表响应不包含 user_id')]
    public function collection_list_hides_user_id(): void
    {
        $this->actingAsUser();

        Collection::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/collections')->assertOk();

        $this->assertArrayNotHasKey('user_id', $response->json('data.0'));
    }

    #[Test]
    #[TestDox('登录用户可取消自己的收藏')]
    public function authenticated_user_can_delete_own_collection(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $collection = Collection::factory()->forSource($source)->create([
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson('/api/v1/collections/'.$collection->id)->assertNoContent();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertSame(0, $source->fresh()->collection_count);
    }

    #[Test]
    #[TestDox('用户无法取消他人的收藏')]
    public function user_cannot_delete_others_collection(): void
    {
        $this->actingAsUser();

        $collection = Collection::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->deleteJson('/api/v1/collections/'.$collection->id)->assertForbidden();

        $this->assertDatabaseHas('collections', ['id' => $collection->id]);
    }

    /**
     * 需要认证的收藏接口清单
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '收藏列表' => ['getJson', '/api/v1/collections'],
            '添加收藏' => ['postJson', '/api/v1/collections'],
            '取消收藏' => ['deleteJson', '/api/v1/collections/1'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问收藏接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_collection_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }
}
