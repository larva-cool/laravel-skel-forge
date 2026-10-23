<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\ContentReviewer;
use App\Ai\Agents\Copywriter;
use App\Enums\SensitiveLevel;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\System\Feedback;
use App\Services\SensitiveWordService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * AI 内容辅助服务
 *
 * 复用 laravel/ai 的一次性生成能力，为运营与审核场景提供 AI 辅助：
 * - 公告 / Banner 文案生成，Banner 配图生成（Seedream）
 * - 用户反馈回复草拟、举报内容智能初判
 * - 敏感词命中后的语境复核（减少误杀）
 *
 * 所有结果均为辅助建议，最终处置由人工完成。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ContentAssistService
{
    public function __construct(
        protected SensitiveWordService $sensitiveWordService,
    ) {}

    /**
     * 生成公告文案
     *
     * @return array{title: string, summary: string, content: string}
     */
    public function generateAnnouncementCopy(string $brief): array
    {
        $data = $this->decodeJsonObject(Copywriter::make()->prompt(<<<PROMPT
        请根据以下需求撰写一则 App 站内公告，输出 JSON：
        {"title":"公告标题（20 字以内）","summary":"公告摘要（50 字以内，用于列表展示）","content":"公告正文（HTML 格式，使用 <p>/<strong>/<ul><li> 等基础标签，3-5 个段落）"}

        需求：{$brief}
        PROMPT)->text);

        return [
            'title' => $this->stringValue($data, 'title'),
            'summary' => $this->stringValue($data, 'summary'),
            'content' => $this->stringValue($data, 'content'),
        ];
    }

    /**
     * 生成 Banner 文案与绘图提示词
     *
     * @return array{title: string, image_prompt: string}
     */
    public function generateBannerCopy(string $brief): array
    {
        $data = $this->decodeJsonObject(Copywriter::make()->prompt(<<<PROMPT
        请根据以下需求为 App 首页 Banner（轮播图）撰写文案并设计配图方案，输出 JSON：
        {"title":"Banner 标题（12 字以内，醒目有力）","image_prompt":"配图的中文绘图提示词（描述画面主体、风格、色调，80 字以内，供 AI 绘图使用；画面中不要出现文字）"}

        需求：{$brief}
        PROMPT)->text);

        return [
            'title' => $this->stringValue($data, 'title'),
            'image_prompt' => $this->stringValue($data, 'image_prompt'),
        ];
    }

    /**
     * 生成 Banner 配图并落盘
     *
     * @return array{path: string, url: string}
     */
    public function generateBannerImage(string $prompt): array
    {
        $path = 'banners/ai/'.Str::uuid()->toString().'.png';

        $stored = Image::of($prompt)->landscape()->timeout(300)->generate()->storePublicly($path);

        if ($stored === false) {
            throw new UnprocessableEntityHttpException('Banner 图片保存失败，请重试');
        }

        return [
            'path' => $path,
            'url' => Storage::url($path),
        ];
    }

    /**
     * 草拟用户反馈的处理回复
     */
    public function draftFeedbackReply(Feedback $feedback): string
    {
        $type = $feedback->type ?: '其他';

        return trim(ContentReviewer::make()->prompt(<<<PROMPT
        你是一名 App 产品的客服主管，请针对以下用户反馈草拟一段回复（100-200 字，真诚、具体、有解决动作，避免套话）。
        直接输出回复正文文本，不要输出 JSON。

        反馈类型：{$type}
        反馈标题：{$feedback->title}
        反馈内容：{$feedback->content}
        PROMPT)->text);
    }

    /**
     * 举报内容智能初判
     *
     * @return array{suggestion: string, suggestion_label: string, reason: string}
     */
    public function triageReport(Report $report): array
    {
        $report->loadMissing('reportable');
        $targetType = $report->reportable_type ?: '未知类型';
        $targetContent = $this->resolveReportableContent($report);
        $reason = $report->reason ?: '（未填写）';

        $data = $this->decodeJsonObject(ContentReviewer::make()->prompt(<<<PROMPT
        请对以下用户举报进行初步判断，输出 JSON：
        {"suggestion":"uphold 或 reject","reason":"判断理由（80 字以内，说明依据；证据不足时说明不确定性）"}
        - uphold=举报成立（内容确实违规），reject=举报不成立（内容未违规或证据不足）

        举报分类：{$report->category}
        举报人描述：{$reason}
        被举报对象类型：{$targetType}
        被举报内容：
        {$targetContent}
        PROMPT)->text);

        $suggestion = $this->stringValue($data, 'suggestion') === 'uphold' ? 'uphold' : 'reject';

        return [
            'suggestion' => $suggestion,
            'suggestion_label' => $suggestion === 'uphold' ? '举报成立' : '举报不成立',
            'reason' => $this->stringValue($data, 'reason'),
        ];
    }

    /**
     * 评论敏感词命中智能复核（减少误杀）
     *
     * 先以本地 DFA 词典找出命中词，再交由 AI 结合语境复核是否真实违规。
     *
     * @return array{suggestion: string, suggestion_label: string, reason: string, hits: array<int, array{word: string, level: string, category: string}>}
     */
    public function reviewComment(Comment $comment): array
    {
        $moderation = $this->sensitiveWordService->detect($comment->content);

        $hits = array_map(static fn (array $hit): array => [
            'word' => $hit['word'],
            'level' => $hit['level'] instanceof SensitiveLevel ? $hit['level']->value : (string) $hit['level'],
            'category' => $hit['category'] instanceof \App\Enums\SensitiveCategory ? $hit['category']->value : (string) $hit['category'],
        ], $moderation->hits);

        // 无命中词时无需 AI 复核
        if ($hits === []) {
            return [
                'suggestion' => 'approve',
                'suggestion_label' => '建议通过',
                'reason' => '未命中任何敏感词，内容无需复核。',
                'hits' => [],
            ];
        }

        $hitsText = collect($hits)->map(static fn (array $hit): string => "「{$hit['word']}」（级别：{$hit['level']}）")->implode('、');

        $data = $this->decodeJsonObject(ContentReviewer::make()->prompt(<<<PROMPT
        以下用户评论命中了敏感词，请结合语境复核是否属于真实违规，输出 JSON：
        {"suggestion":"approve 或 reject","reason":"复核理由（80 字以内）"}
        - approve=误杀（正常表达，如商品名、成语、日常用语中恰好包含敏感词片段），reject=真实违规
        - 注意：命中级别为 review 的词仅表示「需要人工确认」，不代表一定违规

        命中敏感词：{$hitsText}
        评论内容：
        {$comment->content}
        PROMPT)->text);

        $suggestion = $this->stringValue($data, 'suggestion') === 'reject' ? 'reject' : 'approve';

        return [
            'suggestion' => $suggestion,
            'suggestion_label' => $suggestion === 'reject' ? '建议驳回' : '建议通过',
            'reason' => $this->stringValue($data, 'reason'),
            'hits' => $hits,
        ];
    }

    /**
     * 提取被举报对象的文本内容
     */
    protected function resolveReportableContent(Report $report): string
    {
        $reportable = $report->reportable;

        if (! $reportable) {
            return '（被举报对象已删除或不存在）';
        }

        $content = $reportable->getAttribute('content') ?? $reportable->getAttribute('title');

        return $content ? Str::limit((string) $content, 1000) : '（该对象无文本内容可供判断）';
    }

    /**
     * 从 AI 响应文本中解析 JSON 对象
     *
     * 兼容模型输出的 Markdown 代码块包裹与前后多余文本。
     *
     * @return array<string, mixed>
     */
    protected function decodeJsonObject(string $text): array
    {
        $text = trim($text);

        // 去除 Markdown 代码块标记
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $text = $matches[1];
        } elseif (preg_match('/(\{.*\})/s', $text, $matches)) {
            $text = $matches[1];
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new UnprocessableEntityHttpException('AI 生成结果解析失败，请重试');
        }

        return $data;
    }

    /**
     * 安全提取字符串字段
     *
     * @param  array<string, mixed>  $data
     */
    protected function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
