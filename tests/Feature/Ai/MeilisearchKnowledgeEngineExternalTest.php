<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Engines\MeilisearchKnowledgeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Meilisearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Meilisearch 检索引擎真实服务集成测试
 *
 * 默认跳过：设置 KNOWLEDGE_MEILI_TEST_HOST（如 http://192.168.0.116:7700）
 * 后通过 --group=external 运行，验证索引设置、同步与向量检索全链路。
 */
#[CoversClass(MeilisearchKnowledgeEngine::class)]
#[Group('external')]
#[Group('knowledge')]
class MeilisearchKnowledgeEngineExternalTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private MeilisearchKnowledgeEngine $engine;

    private string $indexUid;

    protected function setUp(): void
    {
        parent::setUp();

        $host = env('KNOWLEDGE_MEILI_TEST_HOST');
        if (! is_string($host) || $host === '') {
            $this->markTestSkipped('未配置 KNOWLEDGE_MEILI_TEST_HOST，跳过外部服务集成测试。');
        }

        $this->client = new Client($host, env('KNOWLEDGE_MEILI_TEST_KEY'));
        $this->indexUid = 'test_knowledge_chunks_'.uniqid();
        // 测试向量用 3 维，与生产 embedder 维度配置无关
        $this->engine = new MeilisearchKnowledgeEngine($this->client, $this->indexUid, 'default', 3);
    }

    protected function tearDown(): void
    {
        if (isset($this->client)) {
            $this->client->deleteIndex($this->indexUid);
        }

        parent::tearDown();
    }

    #[Test]
    #[TestDox('全链路：同步分块 → 向量检索按相关度排序 → 移除后不可召回')]
    public function sync_search_and_remove_roundtrip(): void
    {
        $apple = KnowledgeDocument::factory()->ready()->create(['title' => '苹果文档']);
        $apple->chunks()->create(['content' => '苹果是一种水果。', 'embedding' => [1.0, 0.0, 0.0], 'tokens' => 1, 'sort' => 0]);

        $banana = KnowledgeDocument::factory()->ready()->create(['title' => '香蕉文档']);
        $banana->chunks()->create(['content' => '香蕉富含钾元素。', 'embedding' => [0.0, 1.0, 0.0], 'tokens' => 1, 'sort' => 0]);

        // 停用文档同步后不应出现在索引中
        $disabled = KnowledgeDocument::factory()->ready()->disabled()->create(['title' => '停用文档']);
        $disabled->chunks()->create(['content' => '苹果停用内容。', 'embedding' => [1.0, 0.0, 0.0], 'tokens' => 1, 'sort' => 0]);

        $this->engine->syncDocument($apple);
        $this->engine->syncDocument($banana);
        $this->engine->syncDocument($disabled);

        $this->waitForDocumentCount(2);

        // 查询向量与苹果同向 → 苹果第一，香蕉第二，停用文档缺席
        $results = $this->engine->search('苹果', [1.0, 0.0, 0.0], 5);

        $this->assertCount(2, $results);
        $this->assertSame('苹果文档', $results[0]['document_title']);
        $this->assertSame($apple->chunks()->first()->id, $results[0]['chunk_id']);
        $this->assertSame('香蕉文档', $results[1]['document_title']);
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);

        // 移除后不可召回
        $this->engine->removeDocument($apple->id);
        $this->waitForDocumentCount(1);

        $results = $this->engine->search('苹果', [1.0, 0.0, 0.0], 5);
        $this->assertCount(1, $results);
        $this->assertSame('香蕉文档', $results[0]['document_title']);
    }

    #[Test]
    #[TestDox('重建索引时旧分块 ID 被清理')]
    public function resync_replaces_stale_chunk_ids(): void
    {
        $document = KnowledgeDocument::factory()->ready()->create(['title' => '文档']);
        $stale = $document->chunks()->create(['content' => '旧分块', 'embedding' => [1.0, 0.0, 0.0], 'tokens' => 1, 'sort' => 0]);

        $this->engine->syncDocument($document);
        $this->waitForDocumentCount(1);

        // 模拟重建：删除旧分块、创建新分块后再次同步
        $stale->delete();
        $fresh = $document->chunks()->create(['content' => '新分块', 'embedding' => [0.0, 1.0, 0.0], 'tokens' => 1, 'sort' => 0]);
        $this->engine->syncDocument($document->refresh());
        $this->waitForDocumentCount(1);

        $results = $this->engine->search('分块', [0.0, 1.0, 0.0], 5);
        $this->assertCount(1, $results);
        $this->assertSame($fresh->id, $results[0]['chunk_id']);
        $this->assertSame('新分块', $results[0]['content']);
    }

    /**
     * 轮询等待索引文档数达到预期（Meilisearch 写入为异步任务）。
     */
    private function waitForDocumentCount(int $expected): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            if ($this->client->index($this->indexUid)->stats()['numberOfDocuments'] === $expected) {
                // 文档数达标后再等设置类任务收尾，避免 filter/embedder 尚未生效
                usleep(300_000);

                return;
            }
            usleep(100_000);
        }

        $this->fail("等待索引 {$this->indexUid} 文档数达到 {$expected} 超时。");
    }
}
