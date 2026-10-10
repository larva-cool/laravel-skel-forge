<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\VisitController;
use App\Models\Content\Visit;
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
 * 访问记录接口测试目标模型
 *
 * @property int $id
 */
class ApiVisitableStub extends Model
{
    protected $table = 'api_visitable_stubs';

    protected $guarded = [];
}

/**
 * 访问记录接口功能测试
 */
#[CoversClass(VisitController::class)]
#[Group('api')]
#[Group('visit')]
class VisitControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['api_visitable_stub' => ApiVisitableStub::class]);

        Schema::create('api_visitable_stubs', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('api_visitable_stubs');
        parent::tearDown();
    }

    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    private function createSource(): ApiVisitableStub
    {
        return ApiVisitableStub::create([]);
    }

    #[Test]
    #[TestDox('登录用户可记录一次访问')]
    public function authenticated_user_can_record_visit(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $response = $this->postJson('/api/v1/visits', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
        ])->assertCreated();

        $response
            ->assertJsonPath('source_type', 'api_visitable_stub')
            ->assertJsonPath('source_id', $source->id)
            ->assertJsonStructure(['id', 'source_type', 'source_id', 'ip', 'created_at']);

        $this->assertDatabaseHas('visits', [
            'user_id' => $this->user->id,
            'source_type' => 'api_visitable_stub',
            'source_id' => $source->id,
        ]);
    }

    #[Test]
    #[TestDox('同用户对同资源可重复记录访问，不做唯一校验')]
    public function same_user_can_record_visit_for_same_source_multiple_times(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        Visit::create([
            'user_id' => $this->user->id,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'ip' => '127.0.0.1',
        ]);

        $this->postJson('/api/v1/visits', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
        ])->assertCreated();

        $this->assertSame(2, Visit::where('user_id', $this->user->id)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->id)
            ->count());
    }

    #[Test]
    #[TestDox('记录访问缺少必填字段返回 422')]
    public function record_requires_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/visits', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type', 'source_id']);
    }

    #[Test]
    #[TestDox('source_type 命中白名单外值返回 422')]
    public function record_rejects_invalid_source_type(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/visits', [
            'source_type' => 'not_registered_type',
            'source_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type']);
    }

    #[Test]
    #[TestDox('登录用户可获取自己的访问记录列表')]
    public function authenticated_user_can_list_own_visits(): void
    {
        $this->actingAsUser();

        Visit::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Visit::factory()->forSource($this->createSource())->create(['user_id' => $this->user->id]);
        Visit::factory()->forSource($this->createSource())->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->getJson('/api/v1/user/visits')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('我的访问列表支持按 source_type 过滤')]
    public function my_visits_can_be_filtered_by_source_type(): void
    {
        $this->actingAsUser();
        $stubA = $this->createSource();
        $stubB = $this->createSource();

        Relation::morphMap([
            'api_visitable_stub' => ApiVisitableStub::class,
            'api_other_stub' => ApiVisitableStub::class,
        ]);

        Visit::create([
            'user_id' => $this->user->id,
            'source_type' => 'api_visitable_stub',
            'source_id' => $stubA->id,
            'ip' => '127.0.0.1',
        ]);
        Visit::create([
            'user_id' => $this->user->id,
            'source_type' => 'api_other_stub',
            'source_id' => $stubB->id,
            'ip' => '127.0.0.1',
        ]);

        $this->getJson('/api/v1/user/visits?source_type=api_visitable_stub')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source_type', 'api_visitable_stub');
    }

    #[Test]
    #[TestDox('我的访问列表 source_type 不在白名单返回 422')]
    public function my_visits_rejects_invalid_source_type(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/user/visits?source_type=not_registered_type')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '记录访问' => ['postJson', '/api/v1/visits'],
            '我的访问列表' => ['getJson', '/api/v1/user/visits'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问访问记录接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_visit_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri, [])->assertUnauthorized();
    }
}
