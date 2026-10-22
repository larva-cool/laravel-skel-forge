<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Ai\Agents\ContentReviewer;
use App\Ai\Agents\Copywriter;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\ReportController;
use App\Models\Admin\Admin;
use App\Models\Content\Comment;
use App\Models\System\SensitiveWord;
use App\Models\User;
use Database\Factories\Content\ReportFactory;
use Database\Factories\FeedbackFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * AI 辅助内容生产相关接口功能测试
 */
#[CoversClass(AnnouncementController::class)]
#[CoversClass(BannerController::class)]
#[CoversClass(FeedbackController::class)]
#[CoversClass(ReportController::class)]
#[CoversClass(CommentController::class)]
#[Group('admin')]
#[Group('content-assist')]
class AiAssistEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条评论（满足 user_id 与多态源非空约束）
     */
    private function createComment(array $attributes = []): Comment
    {
        return Comment::factory()->forSource(User::factory()->create())->create(array_merge([
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    // ---------------------------------------------------------------------
    // 公告文案生成
    // ---------------------------------------------------------------------

    #[Test]
    #[TestDox('未登录调用公告文案生成返回 401')]
    public function guest_cannot_generate_announcement_copy(): void
    {
        $this->postJson('/admin/announcements/ai-copy', ['brief' => '测试需求'])
            ->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可生成公告文案')]
    public function admin_can_generate_announcement_copy(): void
    {
        Copywriter::fake(['{"title":"会员日特惠","summary":"积分双倍","content":"<p>正文</p>"}']);

        $this->actingAsAdmin()->postJson('/admin/announcements/ai-copy', ['brief' => '周五会员日积分双倍'])
            ->assertOk()
            ->assertJsonPath('title', '会员日特惠')
            ->assertJsonPath('summary', '积分双倍')
            ->assertJsonPath('content', '<p>正文</p>');
    }

    #[Test]
    #[TestDox('公告文案生成缺少 brief 返回 422')]
    public function announcement_copy_requires_brief(): void
    {
        $this->actingAsAdmin()->postJson('/admin/announcements/ai-copy', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brief']);
    }

    // ---------------------------------------------------------------------
    // Banner 文案与配图生成
    // ---------------------------------------------------------------------

    #[Test]
    #[TestDox('管理员可生成 Banner 文案（标题 + 绘图提示词）')]
    public function admin_can_generate_banner_copy(): void
    {
        Copywriter::fake(['{"title":"夏季狂欢节","image_prompt":"海滩派对场景"}']);

        $this->actingAsAdmin()->postJson('/admin/banners/ai-copy', ['brief' => '夏季促销'])
            ->assertOk()
            ->assertJsonPath('title', '夏季狂欢节')
            ->assertJsonPath('image_prompt', '海滩派对场景');
    }

    #[Test]
    #[TestDox('管理员可生成 Banner 配图并获取访问 URL')]
    public function admin_can_generate_banner_image(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Image::fake();

        $response = $this->actingAsAdmin()->postJson('/admin/banners/ai-image', ['prompt' => '海滩派对场景']);

        $response->assertOk()->assertJsonStructure(['path', 'url']);
        Storage::disk('public')->assertExists($response->json('path'));
    }

    #[Test]
    #[TestDox('Banner 配图生成缺少 prompt 返回 422')]
    public function banner_image_requires_prompt(): void
    {
        $this->actingAsAdmin()->postJson('/admin/banners/ai-image', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    // ---------------------------------------------------------------------
    // 反馈回复草拟
    // ---------------------------------------------------------------------

    #[Test]
    #[TestDox('管理员可草拟反馈回复')]
    public function admin_can_draft_feedback_reply(): void
    {
        ContentReviewer::fake(['感谢您的反馈，我们已安排处理。']);
        $feedback = FeedbackFactory::new()->create();

        $this->actingAsAdmin()->postJson("/admin/feedbacks/{$feedback->id}/draft-reply")
            ->assertOk()
            ->assertJsonPath('draft', '感谢您的反馈，我们已安排处理。');
    }

    #[Test]
    #[TestDox('草拟不存在反馈的回复返回 404')]
    public function draft_reply_for_missing_feedback_returns_404(): void
    {
        $this->actingAsAdmin()->postJson('/admin/feedbacks/999999/draft-reply')
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // 举报智能初判
    // ---------------------------------------------------------------------

    #[Test]
    #[TestDox('管理员可对举报进行 AI 初判')]
    public function admin_can_ai_triage_report(): void
    {
        $comment = $this->createComment(['content' => '加微信领红包']);
        $report = ReportFactory::new()->forComment($comment)->create();

        ContentReviewer::fake(['{"suggestion":"uphold","reason":"广告引流内容"}']);

        $this->actingAsAdmin()->postJson("/admin/reports/{$report->id}/ai-triage")
            ->assertOk()
            ->assertJsonPath('suggestion', 'uphold')
            ->assertJsonPath('suggestion_label', '举报成立')
            ->assertJsonPath('reason', '广告引流内容');
    }

    #[Test]
    #[TestDox('已结案举报不允许 AI 初判')]
    public function ai_triage_rejects_closed_report(): void
    {
        $report = ReportFactory::new()->resolved()->forComment($this->createComment())->create();

        $this->actingAsAdmin()->postJson("/admin/reports/{$report->id}/ai-triage")
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // 评论敏感词智能复核
    // ---------------------------------------------------------------------

    #[Test]
    #[TestDox('管理员可对评论进行 AI 敏感词复核')]
    public function admin_can_ai_review_comment(): void
    {
        SensitiveWord::factory()->review()->create(['word' => '垃圾']);
        $comment = $this->createComment(['content' => '这个垃圾处理器质量很好', 'status' => \App\Enums\ReviewStatus::PENDING]);

        ContentReviewer::fake(['{"suggestion":"approve","reason":"商品名称，属于误杀"}']);

        $this->actingAsAdmin()->postJson("/admin/comments/{$comment->id}/ai-review")
            ->assertOk()
            ->assertJsonPath('suggestion', 'approve')
            ->assertJsonPath('reason', '商品名称，属于误杀')
            ->assertJsonPath('hits.0.word', '垃圾');
    }

    #[Test]
    #[TestDox('AI 复核不命中敏感词的评论直接建议通过')]
    public function ai_review_approves_comment_without_hits(): void
    {
        ContentReviewer::fake();
        $comment = $this->createComment(['content' => '正常评论内容']);

        $this->actingAsAdmin()->postJson("/admin/comments/{$comment->id}/ai-review")
            ->assertOk()
            ->assertJsonPath('suggestion', 'approve')
            ->assertJsonPath('hits', []);
    }
}
