<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\Content\CommentController;
use App\Models\Content\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 评论接口功能测试
 */
#[CoversClass(CommentController::class)]
#[Group('api')]
#[Group('comment')]
class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    /**
     * 创建一个可被评论的目标
     */
    private function createSource(): User
    {
        return User::factory()->create();
    }

    #[Test]
    #[TestDox('登录用户可发布评论')]
    public function authenticated_user_can_store_comment(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $this->postJson('/api/v1/comments', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'content' => '这是一条评论',
            'mentioned_users' => [$source->id],
        ])
            ->assertCreated()
            ->assertJsonPath('source_type', 'user')
            ->assertJsonPath('source_id', $source->id)
            ->assertJsonPath('content', '这是一条评论')
            ->assertJsonPath('mentioned_users.0', $source->id);

        $this->assertDatabaseHas('comments', [
            'user_id' => $this->user->id,
            'source_type' => 'user',
            'source_id' => $source->id,
            'content' => '这是一条评论',
        ]);
    }

    #[Test]
    #[TestDox('评论缺少必填字段返回 422')]
    public function store_requires_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/comments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_type', 'source_id', 'content']);
    }

    #[Test]
    #[TestDox('评论内容超过 1000 字符返回 422')]
    public function store_rejects_too_long_content(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();

        $this->postJson('/api/v1/comments', [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'content' => str_repeat('a', 1001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    #[Test]
    #[TestDox('登录用户可按 source 过滤评论列表')]
    public function authenticated_user_can_filter_comments(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();
        $other = $this->createSource();

        Comment::factory()->forSource($source)->create(['user_id' => $this->user->id, 'content' => 'A']);
        Comment::factory()->forSource($other)->create(['user_id' => $this->user->id, 'content' => 'B']);

        $this->getJson('/api/v1/comments?source_type=user&source_id='.$source->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.content', 'A');
    }

    #[Test]
    #[TestDox('用户可删除自己的评论')]
    public function user_can_delete_own_comment(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();
        $comment = Comment::factory()->forSource($source)->create(['user_id' => $this->user->id]);

        $this->deleteJson('/api/v1/comments/'.$comment->id)->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    #[Test]
    #[TestDox('用户无法删除他人的评论')]
    public function user_cannot_delete_others_comment(): void
    {
        $this->actingAsUser();
        $source = $this->createSource();
        $comment = Comment::factory()->forSource($source)->create(['user_id' => User::factory()->create()->id]);

        $this->deleteJson('/api/v1/comments/'.$comment->id)->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '评论列表' => ['getJson', '/api/v1/comments'],
            '发布评论' => ['postJson', '/api/v1/comments'],
            '删除评论' => ['deleteJson', '/api/v1/comments/1'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问评论接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_comment_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }
}
