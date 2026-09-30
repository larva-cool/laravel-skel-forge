<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\ReportController;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\User as UserModel;
use Database\Factories\Content\CommentFactory;
use Database\Factories\Content\ReportFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户端举报接口功能测试
 */
#[CoversClass(ReportController::class)]
#[Group('api')]
#[Group('report')]
class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserModel::factory()->create();
    }

    private function actingAsUser(): self
    {
        return $this->actingAs($this->user, 'sanctum');
    }

    #[Test]
    #[TestDox('游客提交举报返回未授权')]
    public function guest_cannot_submit_report(): void
    {
        $comment = $this->makeComment();

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
            'reason' => '广告内容',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('reports', 0);
    }

    #[Test]
    #[TestDox('登录用户举报评论成功（status=pending, 自动绑定 user_id）')]
    public function authenticated_user_can_report_comment(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
            'reason' => '明显的广告内容',
            'evidence' => ['https://cdn.example.com/a.jpg'],
        ]);

        $response->assertCreated();

        $report = Report::query()->first();
        $this->assertNotNull($report);
        $this->assertSame($this->user->id, $report->user_id);
        $this->assertSame($comment->getMorphClass(), $report->reportable_type);
        $this->assertSame($comment->id, $report->reportable_id);
        $this->assertSame('ad', $report->category);
        $this->assertSame(\App\Enums\ReportStatus::PENDING, $report->status);
    }

    #[Test]
    #[TestDox('举报用户目标类型成功（target_type=user）')]
    public function can_report_user_target(): void
    {
        $target = UserModel::factory()->create();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'user',
            'target_id' => $target->id,
            'category' => 'insult',
            'reason' => '人身攻击',
        ]);

        $response->assertCreated();
        $report = Report::query()->first();
        $this->assertSame($target->getMorphClass(), $report->reportable_type);
        $this->assertSame($target->id, $report->reportable_id);
    }

    #[Test]
    #[TestDox('举报不存在的目标返回 422')]
    public function cannot_report_nonexistent_target(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => 999999,
            'category' => 'ad',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('reports', 0);
    }

    #[Test]
    #[TestDox('不支持的 target_type 返回 422')]
    public function invalid_target_type_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'unsupported',
            'target_id' => 1,
            'category' => 'ad',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('非法 category 返回 422')]
    public function invalid_category_returns_422(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'unknown_category',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('同一用户对同一未结案对象重复举报返回 422')]
    public function duplicate_pending_report_is_rejected(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        // 第一次
        $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
        ])->assertCreated();

        // 第二次（pending 状态）
        $second = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'pornography',
        ]);

        $second->assertStatus(422);
        $this->assertSame(1, Report::query()->count());
    }

    #[Test]
    #[TestDox('已驳回的举报不阻止重新举报（再次允许提交）')]
    public function rejected_report_does_not_block_new_report(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        // 先创建一条已驳回
        ReportFactory::new()->forTarget($comment)->create([
            'user_id' => $this->user->id,
            'status' => \App\Enums\ReportStatus::REJECTED,
        ]);

        // 重新举报可成功
        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
        ]);

        $response->assertCreated();
        $this->assertSame(2, Report::query()->count());
    }

    #[Test]
    #[TestDox('不同用户对同一对象可重复举报')]
    public function different_users_can_report_same_target(): void
    {
        $comment = $this->makeComment();
        $other = UserModel::factory()->create();

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
        ])->assertCreated();

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'insult',
        ])->assertCreated();

        $this->assertSame(2, Report::query()->count());
    }

    #[Test]
    #[TestDox('我的举报列表仅返回当前用户提交的举报')]
    public function my_reports_returns_only_self_reports(): void
    {
        $comment = $this->makeComment();
        $other = UserModel::factory()->create();

        ReportFactory::new()->forTarget($comment)->create(['user_id' => $this->user->id]);
        ReportFactory::new()->forTarget($comment)->create(['user_id' => $other->id]);

        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/user/reports/my');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', Report::query()->where('user_id', $this->user->id)->first()->id);
    }

    #[Test]
    #[TestDox('我的举报列表游客访问返回未授权')]
    public function guest_cannot_list_my_reports(): void
    {
        $this->getJson('/api/v1/user/reports/my')->assertUnauthorized();
    }

    /**
     * 创建评论（带 user_id 与 source 形态）
     */
    private function makeComment(): Comment
    {
        $source = UserModel::factory()->create();

        return Comment::factory()->forSource($source)->create([
            'user_id' => UserModel::factory()->create()->id,
        ]);
    }

    #[Test]
    #[TestDox('evidence 字段超过 9 个 URL 返回 422')]
    public function evidence_too_many_returns_422(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        $evidence = array_fill(0, 10, 'https://cdn.example.com/img.jpg');

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
            'evidence' => $evidence,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('evidence 单个元素非 URL 返回 422')]
    public function evidence_item_invalid_returns_422(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
            'evidence' => ['not-a-url'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('不指定 user 提交时自动绑定当前用户')]
    public function user_id_auto_bound_to_current_user(): void
    {
        $comment = $this->makeComment();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/user/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'category' => 'ad',
            // user_id 不提交
        ]);

        $response->assertCreated();
        $report = Report::query()->first();
        $this->assertSame($this->user->id, $report->user_id);
    }
}