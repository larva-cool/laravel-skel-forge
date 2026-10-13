<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1\Content;

use App\Http\Controllers\Api\V1\Content\FollowController;
use App\Models\Content\Follow;
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
 * 关注接口的测试目标模型
 *
 * @property int $id
 * @property int $follow_count
 */
class ApiFollowableStub extends Model
{
    use \App\Models\Traits\HasFollowable;

    protected $table = 'api_followable_stubs';

    protected $guarded = [];
}

/**
 * 关注接口功能测试
 */
#[CoversClass(FollowController::class)]
#[Group('api')]
#[Group('follow')]
class FollowControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['api_followable_stub' => \Tests\Feature\Api\V1\Content\ApiFollowableStub::class]);

        Schema::create('api_followable_stubs', function ($table) {
            $table->id();
            $table->unsignedInteger('follow_count')->default(0);
            $table->timestamps();
        });

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('api_followable_stubs');
        parent::tearDown();
    }

    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    private function createSource(): \Tests\Feature\Api\V1\Content\ApiFollowableStub
    {
        return \Tests\Feature\Api\V1\Content\ApiFollowableStub::create([]);
    }

    #[Test]
    #[TestDox('登录用户可关注')]
    public function authenticated_user_can_store_follow(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $this->postJson('/api/v1/follows', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'extra' => ['from' => 'app'],
        ])
            ->assertCreated()
            ->assertJsonPath('source_type', 'api_followable_stub')
            ->assertJsonPath('source_id', $source->id)
            ->assertJsonPath('extra.from', 'app');

        $this->assertDatabaseHas('follows', [
            'user_id' => $this->user->id,
            'source_type' => 'api_followable_stub',
            'source_id' => $source->id,
        ]);
        $this->assertSame(1, $source->fresh()->follow_count);
    }

    #[Test]
    #[TestDox('关注缺少必填字段返回 422')]
    public function store_requires_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/follows', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type', 'source_id']);
    }

    #[Test]
    #[TestDox('重复关注同一资源返回 422')]
    public function store_rejects_duplicate_follow(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        Follow::factory()->forSource($source)->create(['user_id' => $this->user->id]);

        $this->postJson('/api/v1/follows', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_id']);
    }

    #[Test]
    #[TestDox('登录用户可获取自己的关注列表')]
    public function authenticated_user_can_list_own_follows(): void
    {
        $this->actingAsUser();

        Follow::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Follow::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Follow::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->getJson('/api/v1/follows')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('登录用户可按资源类型过滤关注列表')]
    public function authenticated_user_can_filter_follows_by_source_type(): void
    {
        $this->actingAsUser();

        Follow::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);

        $this->getJson('/api/v1/follows?source_type=api_followable_stub')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/follows?source_type=comment')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    #[Test]
    #[TestDox('登录用户可取消自己的关注')]
    public function authenticated_user_can_delete_own_follow(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $follow = Follow::factory()->forSource($source)->create(['user_id' => $this->user->id]);

        $this->deleteJson('/api/v1/follows/'.$follow->id)->assertNoContent();

        $this->assertDatabaseMissing('follows', ['id' => $follow->id]);
        $this->assertSame(0, $source->fresh()->follow_count);
    }

    #[Test]
    #[TestDox('用户无法取消他人的关注')]
    public function user_cannot_delete_others_follow(): void
    {
        $this->actingAsUser();

        $follow = Follow::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->deleteJson('/api/v1/follows/'.$follow->id)->assertForbidden();
        $this->assertDatabaseHas('follows', ['id' => $follow->id]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '关注列表' => ['getJson', '/api/v1/follows'],
            '添加关注' => ['postJson', '/api/v1/follows'],
            '取消关注' => ['deleteJson', '/api/v1/follows/1'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问关注接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_follow_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }
}
