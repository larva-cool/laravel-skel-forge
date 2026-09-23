<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1\Content;

use App\Http\Controllers\Api\V1\Content\LikeController;
use App\Models\Content\Like;
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
 * 点赞接口的测试目标模型
 *
 * @property int $id
 * @property int $like_count
 */
class ApiLikeableStub extends Model
{
    use \App\Models\Traits\HasLikeable;

    protected $table = 'api_likeable_stubs';

    protected $guarded = [];
}

/**
 * 点赞接口功能测试
 */
#[CoversClass(LikeController::class)]
#[Group('api')]
#[Group('like')]
class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['api_likeable_stub' => \Tests\Feature\Api\V1\ApiLikeableStub::class]);

        Schema::create('api_likeable_stubs', function ($table) {
            $table->id();
            $table->unsignedInteger('like_count')->default(0);
            $table->timestamps();
        });

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('api_likeable_stubs');
        parent::tearDown();
    }

    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    private function createSource(): \Tests\Feature\Api\V1\ApiLikeableStub
    {
        return \Tests\Feature\Api\V1\ApiLikeableStub::create([]);
    }

    #[Test]
    #[TestDox('登录用户可点赞')]
    public function authenticated_user_can_store_like(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $this->postJson('/api/v1/likes', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'extra' => ['from' => 'app'],
        ])
            ->assertCreated()
            ->assertJsonPath('source_type', 'api_likeable_stub')
            ->assertJsonPath('source_id', $source->id)
            ->assertJsonPath('extra.from', 'app');

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'source_type' => 'api_likeable_stub',
            'source_id' => $source->id,
        ]);
        $this->assertSame(1, $source->fresh()->like_count);
    }

    #[Test]
    #[TestDox('点赞缺少必填字段返回 422')]
    public function store_requires_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/likes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type', 'source_id']);
    }

    #[Test]
    #[TestDox('重复点赞同一资源返回 422')]
    public function store_rejects_duplicate_like(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        Like::factory()->forSource($source)->create(['user_id' => $this->user->id]);

        $this->postJson('/api/v1/likes', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_id']);
    }

    #[Test]
    #[TestDox('登录用户可获取自己的点赞列表')]
    public function authenticated_user_can_list_own_likes(): void
    {
        $this->actingAsUser();

        Like::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Like::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Like::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->getJson('/api/v1/likes')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('登录用户可取消自己的点赞')]
    public function authenticated_user_can_delete_own_like(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $like = Like::factory()->forSource($source)->create(['user_id' => $this->user->id]);

        $this->deleteJson('/api/v1/likes/'.$like->id)->assertNoContent();

        $this->assertDatabaseMissing('likes', ['id' => $like->id]);
        $this->assertSame(0, $source->fresh()->like_count);
    }

    #[Test]
    #[TestDox('用户无法取消他人的点赞')]
    public function user_cannot_delete_others_like(): void
    {
        $this->actingAsUser();

        $like = Like::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->deleteJson('/api/v1/likes/'.$like->id)->assertForbidden();
        $this->assertDatabaseHas('likes', ['id' => $like->id]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '点赞列表' => ['getJson', '/api/v1/likes'],
            '添加点赞' => ['postJson', '/api/v1/likes'],
            '取消点赞' => ['deleteJson', '/api/v1/likes/1'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问点赞接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_like_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }
}
