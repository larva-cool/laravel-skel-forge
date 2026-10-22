<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use App\Ai\Agents\ContentReviewer;
use App\Ai\Agents\Copywriter;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\System\SensitiveWord;
use App\Services\Ai\ContentAssistService;
use Database\Factories\FeedbackFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

/**
 * AI 内容辅助服务测试
 */
#[CoversClass(ContentAssistService::class)]
#[Group('ai')]
#[Group('content-assist')]
class ContentAssistServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentAssistService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ContentAssistService::class);
    }

    #[Test]
    #[TestDox('公告文案生成：解析 AI 返回的 JSON 为标题/摘要/正文')]
    public function generate_announcement_copy_parses_json(): void
    {
        Copywriter::fake([json_encode([
            'title' => '会员日特惠开启',
            'summary' => '本周五会员日，积分双倍',
            'content' => '<p>亲爱的用户：</p><p>本周五会员日。</p>',
        ], JSON_UNESCAPED_UNICODE)]);

        $result = $this->service->generateAnnouncementCopy('周五会员日积分双倍活动');

        $this->assertSame('会员日特惠开启', $result['title']);
        $this->assertSame('本周五会员日，积分双倍', $result['summary']);
        $this->assertStringContainsString('<p>', $result['content']);
    }

    #[Test]
    #[TestDox('公告文案生成：兼容 Markdown 代码块包裹的 JSON')]
    public function generate_announcement_copy_parses_markdown_wrapped_json(): void
    {
        Copywriter::fake(["```json\n{\"title\":\"标题\",\"summary\":\"摘要\",\"content\":\"<p>正文</p>\"}\n```"]);

        $result = $this->service->generateAnnouncementCopy('测试需求');

        $this->assertSame('标题', $result['title']);
    }

    #[Test]
    #[TestDox('公告文案生成：AI 返回非 JSON 时抛出 422 异常')]
    public function generate_announcement_copy_throws_on_invalid_json(): void
    {
        Copywriter::fake(['这不是 JSON']);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service->generateAnnouncementCopy('测试需求');
    }

    #[Test]
    #[TestDox('Banner 文案生成：返回标题与绘图提示词')]
    public function generate_banner_copy_returns_title_and_image_prompt(): void
    {
        Copywriter::fake(['{"title":"夏季狂欢节","image_prompt":"海滩派对场景，明亮色调"}']);

        $result = $this->service->generateBannerCopy('夏季促销活动');

        $this->assertSame('夏季狂欢节', $result['title']);
        $this->assertSame('海滩派对场景，明亮色调', $result['image_prompt']);
    }

    #[Test]
    #[TestDox('Banner 配图生成：绘图落盘并返回访问 URL')]
    public function generate_banner_image_stores_file_and_returns_url(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Image::fake();

        $result = $this->service->generateBannerImage('海滩派对场景');

        $this->assertStringStartsWith('banners/ai/', $result['path']);
        $this->assertStringEndsWith('.png', $result['path']);
        Storage::disk('public')->assertExists($result['path']);
        $this->assertSame(Storage::disk('public')->url($result['path']), $result['url']);
    }

    #[Test]
    #[TestDox('反馈回复草拟：返回 AI 生成的回复文本')]
    public function draft_feedback_reply_returns_text(): void
    {
        ContentReviewer::fake(['感谢您的反馈，我们已在最新版本修复该问题，请升级后重试。']);
        $feedback = FeedbackFactory::new()->create([
            'title' => '无法登录',
            'content' => '输入密码后提示系统错误',
        ]);

        $draft = $this->service->draftFeedbackReply($feedback);

        $this->assertSame('感谢您的反馈，我们已在最新版本修复该问题，请升级后重试。', $draft);
    }

    #[Test]
    #[TestDox('举报初判：返回成立建议与理由')]
    public function triage_report_returns_uphold_suggestion(): void
    {
        $comment = Comment::factory()->create(['content' => '加微信领红包，日赚千元']);
        $report = Report::factory()->forComment($comment)->create(['category' => 'ad']);

        ContentReviewer::fake(['{"suggestion":"uphold","reason":"内容属于广告引流，违反社区规范"}']);

        $result = $this->service->triageReport($report);

        $this->assertSame('uphold', $result['suggestion']);
        $this->assertSame('举报成立', $result['suggestion_label']);
        $this->assertSame('内容属于广告引流，违反社区规范', $result['reason']);
    }

    #[Test]
    #[TestDox('举报初判：非法建议值回退为不成立')]
    public function triage_report_falls_back_to_reject_on_invalid_suggestion(): void
    {
        $report = Report::factory()->create();

        ContentReviewer::fake(['{"suggestion":"unknown","reason":"证据不足"}']);

        $result = $this->service->triageReport($report);

        $this->assertSame('reject', $result['suggestion']);
        $this->assertSame('举报不成立', $result['suggestion_label']);
    }

    #[Test]
    #[TestDox('评论复核：未命中敏感词时直接建议通过且不调用 AI')]
    public function review_comment_approves_without_ai_when_no_hits(): void
    {
        ContentReviewer::fake();
        $comment = Comment::factory()->create(['content' => '这是一条正常的评论内容']);

        $result = $this->service->reviewComment($comment);

        $this->assertSame('approve', $result['suggestion']);
        $this->assertSame([], $result['hits']);
        ContentReviewer::assertNeverPrompted();
    }

    #[Test]
    #[TestDox('评论复核：命中敏感词时交由 AI 语境复核')]
    public function review_comment_calls_ai_when_sensitive_word_hit(): void
    {
        SensitiveWord::factory()->review()->create(['word' => '垃圾']);
        $comment = Comment::factory()->create(['content' => '这个垃圾处理器质量很好']);

        ContentReviewer::fake(['{"suggestion":"approve","reason":"垃圾处理器为商品名称，属于误杀"}']);

        $result = $this->service->reviewComment($comment);

        $this->assertSame('approve', $result['suggestion']);
        $this->assertSame('建议通过', $result['suggestion_label']);
        $this->assertCount(1, $result['hits']);
        $this->assertSame('垃圾', $result['hits'][0]['word']);
        $this->assertSame('review', $result['hits'][0]['level']);
        ContentReviewer::assertPrompted(fn (string $prompt): bool => str_contains($prompt, '垃圾处理器'));
    }
}
