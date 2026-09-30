<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Admin\ReportController;
use App\Models\Admin\Admin;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\User as UserModel;
use Database\Factories\Content\ReportFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台举报处理（含联动处置）功能测试
 */
#[CoversClass(ReportController::class)]
#[Group('admin')]
#[Group('report')]
class ReportControllerProcessTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条用于被举报的评论（带 user_id 与 source）
     */
    private function makeComment(): Comment
    {
        $source = UserModel::factory()->create();

        return Comment::factory()->forSource($source)->create([
            'user_id' => UserModel::factory()->create()->id,
        ]);
    }

    /**
     * 带自定义属性创建评论（user_id 强制注入）
     */
    private function makeCommentWith(array $attrs): Comment
    {
        $attrs['user_id'] ??= UserModel::factory()->create()->id;
        $attrs['source_type'] ??= UserModel::factory()->create()->getMorphClass();
        $attrs['source_id'] ??= UserModel::factory()->create()->id;

        return Comment::factory()->create($attrs);
    }

    #[Test]
    #[TestDox('处理时 status 自动从 pending 流转到 processing，再设为 resolved')]
    public function process_status_flow_pending_to_resolved(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create(['status' => 'pending']);

        $response = $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'remark' => '已处理',
        ]);

        $response->assertOk();
        $report->refresh();
        $this->assertSame('resolved', $report->status->value);
        $this->assertSame($this->admin->id, $report->handler_id);
        $this->assertSame('已处理', $report->process_remark);
        $this->assertNotNull($report->processed_at);
    }

    #[Test]
    #[TestDox('处理时自动写入 handler_id 和 processed_at')]
    public function process_records_handler_and_timestamp(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'rejected',
            'remark' => '驳回',
        ])->assertOk();

        $report->refresh();
        $this->assertSame($this->admin->id, $report->handler_id);
        $this->assertNotNull($report->processed_at);
        $this->assertSame('驳回', $report->process_remark);
        $this->assertSame('rejected', $report->status->value);
    }

    #[Test]
    #[TestDox('已结案的举报再次处理返回 422')]
    public function closed_report_cannot_be_processed(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create(['status' => 'resolved']);

        $response = $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'rejected',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('联动处置 hide_comment 将评论 status 置为 rejected')]
    public function action_hide_comment_marks_comment_rejected(): void
    {
        $comment = $this->makeCommentWith(['status' => ReviewStatus::APPROVED]);
        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [['type' => 'hide_comment']],
        ])->assertOk();

        $comment->refresh();
        $this->assertSame('rejected', $comment->status->value);

        $report->refresh();
        $this->assertSame([['type' => 'hide_comment']], $report->process_actions);
    }

    #[Test]
    #[TestDox('联动处置 delete_comment 递归删除评论及其回复')]
    public function action_delete_comment_removes_comment_and_replies(): void
    {
        $source = UserModel::factory()->create();
        $comment = Comment::factory()->forSource($source)->create([
            'user_id' => UserModel::factory()->create()->id,
        ]);
        $reply = Comment::factory()->forSource($comment)->create([
            'user_id' => UserModel::factory()->create()->id,
        ]);

        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [['type' => 'delete_comment']],
        ])->assertOk();

        $this->assertNull(Comment::query()->find($comment->id));
        $this->assertNull(Comment::query()->find($reply->id));
    }

    #[Test]
    #[TestDox('联动处置 freeze_user 直接冻结被举报用户（永久冻结）')]
    public function action_freeze_user_marks_user_as_frozen(): void
    {
        $target = UserModel::factory()->create(['status' => UserStatus::ACTIVE]);
        $report = ReportFactory::new()->forTarget($target)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [['type' => 'freeze_user']],
        ])->assertOk();

        $target->refresh();
        $this->assertSame(0, $target->status->value); // FROZEN
    }

    #[Test]
    #[TestDox('联动处置 freeze_user 协议不含 duration 字段，客户端传入会被剥离')]
    public function action_freeze_user_duration_is_stripped(): void
    {
        $target = UserModel::factory()->create();
        $report = ReportFactory::new()->forTarget($target)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [['type' => 'freeze_user', 'duration' => 7]],
        ])->assertOk();

        $target->refresh();
        $this->assertSame(UserStatus::FROZEN, $target->status);

        $persisted = Report::query()->find($report->id);
        $this->assertArrayNotHasKey('duration', $persisted->process_actions[0]);
    }

    #[Test]
    #[TestDox('联动处置 freeze_user 在被举报对象为评论时冻结评论作者')]
    public function action_freeze_user_freezes_comment_author_when_target_is_comment(): void
    {
        $author = UserModel::factory()->create();
        $comment = $this->makeCommentWith(['user_id' => $author->id]);
        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [['type' => 'freeze_user']],
        ])->assertOk();

        $author->refresh();
        $this->assertSame(UserStatus::FROZEN, $author->status);
    }

    #[Test]
    #[TestDox('联动处置 rejected 不执行任何动作')]
    public function rejected_status_does_not_execute_actions(): void
    {
        $comment = $this->makeCommentWith(['status' => ReviewStatus::APPROVED]);
        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'rejected',
            'actions' => [['type' => 'hide_comment']],
        ])->assertOk();

        $comment->refresh();
        $this->assertSame('approved', $comment->status->value); // 未变
    }

    #[Test]
    #[TestDox('多个处置动作可同时执行（hide + delete + freeze）')]
    public function multiple_actions_execute_in_order(): void
    {
        $author = UserModel::factory()->create();
        $comment = $this->makeCommentWith(['user_id' => $author->id, 'status' => ReviewStatus::APPROVED]);
        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [
                ['type' => 'hide_comment'],
                ['type' => 'freeze_user'],
            ],
        ])->assertOk();

        $comment->refresh();
        $author->refresh();
        $this->assertSame('rejected', $comment->status->value);
        $this->assertSame(UserStatus::FROZEN, $author->status);
    }

    #[Test]
    #[TestDox('非法 action.type 返回 422')]
    public function invalid_action_type_returns_422(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create();

        $response = $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [['type' => 'ban_user']],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('非法 status 值返回 422')]
    public function invalid_status_returns_422(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create();

        $response = $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'pending',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('处理后 process_actions 持久化到数据库')]
    public function process_actions_persisted(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create();

        $this->actingAsAdmin()->patchJson("/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => [
                ['type' => 'hide_comment'],
                ['type' => 'freeze_user'],
            ],
        ])->assertOk();

        $persisted = Report::query()->find($report->id);
        $this->assertCount(2, $persisted->process_actions);
        $this->assertSame('hide_comment', $persisted->process_actions[0]['type']);
        $this->assertSame('freeze_user', $persisted->process_actions[1]['type']);
        $this->assertArrayNotHasKey('duration', $persisted->process_actions[1]);
    }
}