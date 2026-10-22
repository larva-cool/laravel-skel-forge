<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\KnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 知识库服务测试
 */
#[CoversClass(KnowledgeService::class)]
#[Group('ai')]
#[Group('knowledge')]
class KnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(KnowledgeService::class);
    }

    /**
     * 按关键词返回确定性向量的 Embedding Fake
     */
    private function fakeDeterministicEmbeddings(): void
    {
        Embeddings::fake(function (EmbeddingsPrompt $prompt): array {
            $vectors = [];
            foreach ($prompt->inputs as $input) {
                $vectors[] = match (true) {
                    str_contains((string) $input, '苹果') => array_pad([1.0], $prompt->dimensions, 0.0),
                    str_contains((string) $input, '香蕉') => array_pad([0.0, 1.0], $prompt->dimensions, 0.0),
                    default => array_pad([0.0, 0.0, 1.0], $prompt->dimensions, 0.0),
                };
            }

            return $vectors;
        });
    }

    #[Test]
    #[TestDox('短段落按顺序合并为不超过上限的分块')]
    public function chunk_text_merges_paragraphs_up_to_max_length(): void
    {
        $content = "第一段内容。\n\n第二段内容。\n\n第三段内容。";

        $chunks = $this->service->chunkText($content, 600);

        $this->assertSame(["第一段内容。\n\n第二段内容。\n\n第三段内容。"], $chunks);
    }

    #[Test]
    #[TestDox('段落合并超过上限时拆分为多个分块')]
    public function chunk_text_splits_when_exceeding_max_length(): void
    {
        $content = "短句一。\n\n短句二。";

        $chunks = $this->service->chunkText($content, 8);

        $this->assertSame(['短句一。', '短句二。'], $chunks);
    }

    #[Test]
    #[TestDox('单段超过上限时按上限硬切分')]
    public function chunk_text_hard_splits_overlong_paragraph(): void
    {
        $paragraph = str_repeat('测试', 20); // 40 字符

        $chunks = $this->service->chunkText($paragraph, 15);

        $this->assertCount(3, $chunks);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(15, mb_strlen($chunk));
        }
    }

    #[Test]
    #[TestDox('空内容切分结果为空数组')]
    public function chunk_text_returns_empty_for_blank_content(): void
    {
        $this->assertSame([], $this->service->chunkText("  \n\n  "));
    }

    #[Test]
    #[TestDox('索引文档：分块向量化落库并标记为已完成')]
    public function index_document_chunks_embeds_and_marks_ready(): void
    {
        Embeddings::fake();
        $document = KnowledgeDocument::factory()->create([
            'content' => "苹果是一种水果。\n\n香蕉富含钾元素。",
        ]);

        $this->service->indexDocument($document);

        $document->refresh();
        $this->assertSame(KnowledgeDocumentStatus::READY, $document->status);
        $this->assertSame(1, $document->chunks_count);
        $this->assertSame(1, $document->chunks()->count());
        $this->assertCount(
            (int) config('ai.providers.volc.models.embeddings.dimensions'),
            $document->chunks()->first()->embedding
        );
    }

    #[Test]
    #[TestDox('索引文档：空内容直接标记为已完成且无分块')]
    public function index_document_marks_ready_without_chunks_for_blank_content(): void
    {
        $document = KnowledgeDocument::factory()->create(['content' => "  \n\n  "]);

        $this->service->indexDocument($document);

        $document->refresh();
        $this->assertSame(KnowledgeDocumentStatus::READY, $document->status);
        $this->assertSame(0, $document->chunks_count);
    }

    #[Test]
    #[TestDox('索引文档：重建索引时清理旧分块')]
    public function index_document_replaces_existing_chunks(): void
    {
        Embeddings::fake();
        $document = KnowledgeDocument::factory()->ready()->create([
            'content' => '苹果是一种水果。',
        ]);
        $document->chunks()->create([
            'content' => '旧分块',
            'embedding' => [0.1, 0.2],
            'tokens' => 0,
            'sort' => 0,
        ]);

        $this->service->indexDocument($document);

        $this->assertSame(1, $document->chunks()->count());
        $this->assertSame('苹果是一种水果。', $document->chunks()->first()->content);
    }

    #[Test]
    #[TestDox('索引文档：向量化异常时标记失败并抛出异常')]
    public function index_document_marks_failed_and_rethrows_on_error(): void
    {
        Embeddings::fake(fn () => throw new \RuntimeException('模拟网关异常'));
        $document = KnowledgeDocument::factory()->create(['content' => '苹果是一种水果。']);

        try {
            $this->service->indexDocument($document);
            $this->fail('应抛出 RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('模拟网关异常', $e->getMessage());
        }

        $document->refresh();
        $this->assertSame(KnowledgeDocumentStatus::FAILED, $document->status);
        $this->assertSame('模拟网关异常', $document->error);
    }

    #[Test]
    #[TestDox('语义检索：按余弦相似度降序返回最相关分块')]
    public function search_returns_ranked_chunks_by_similarity(): void
    {
        $this->fakeDeterministicEmbeddings();

        $appleDoc = KnowledgeDocument::factory()->create(['title' => '苹果文档', 'content' => '苹果是一种营养丰富的水果。']);
        $bananaDoc = KnowledgeDocument::factory()->create(['title' => '香蕉文档', 'content' => '香蕉富含钾元素。']);
        $this->service->indexDocument($appleDoc);
        $this->service->indexDocument($bananaDoc);

        $results = $this->service->search('苹果的营养价值', 5);

        $this->assertCount(2, $results);
        $this->assertSame('苹果文档', $results[0]['document_title']);
        $this->assertSame(1.0, round($results[0]['score'], 4));
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
    }

    #[Test]
    #[TestDox('语义检索：停用与未完成索引的文档不参与召回')]
    public function search_excludes_disabled_and_not_ready_documents(): void
    {
        $this->fakeDeterministicEmbeddings();

        $enabledDoc = KnowledgeDocument::factory()->create(['title' => '苹果文档', 'content' => '苹果是一种水果。']);
        $this->service->indexDocument($enabledDoc);

        $disabledDoc = KnowledgeDocument::factory()->disabled()->create(['title' => '停用文档', 'content' => '苹果停用内容。']);
        $this->service->indexDocument($disabledDoc);

        $pendingDoc = KnowledgeDocument::factory()->create(['title' => '待处理文档', 'content' => '苹果待处理内容。']);
        $pendingDoc->chunks()->create([
            'content' => '苹果待处理内容。',
            'embedding' => array_pad([1.0], (int) config('ai.providers.volc.models.embeddings.dimensions'), 0.0),
            'tokens' => 0,
            'sort' => 0,
        ]);

        $results = $this->service->search('苹果', 10);

        $this->assertCount(1, $results);
        $this->assertSame('苹果文档', $results[0]['document_title']);
    }

    #[Test]
    #[TestDox('语义检索：limit 限制返回条数')]
    public function search_respects_limit(): void
    {
        Embeddings::fake();

        $doc = KnowledgeDocument::factory()->create([
            'content' => str_repeat("苹果段落。\n\n", 6),
        ]);
        $this->service->indexDocument($doc);

        $results = $this->service->search('苹果', 2);

        $this->assertLessThanOrEqual(2, $results->count());
    }
}
