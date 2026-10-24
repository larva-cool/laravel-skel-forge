<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Engines;

use App\Models\Ai\KnowledgeChunk;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Contracts\KnowledgeSearchEngine;
use App\Services\Ai\Engines\DatabaseKnowledgeEngine;
use App\Services\Ai\Engines\MeilisearchKnowledgeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Meilisearch 检索引擎测试（客户端 Mock，不触网）
 *
 * 验证索引载荷结构、检索参数组装与同步语义（先清后写、停用即移除）。
 */
#[CoversClass(MeilisearchKnowledgeEngine::class)]
#[Group('ai')]
#[Group('knowledge')]
class MeilisearchKnowledgeEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 构造引擎与客户端 Mock（$expectEnsureIndex 为真时期待 ensureIndex 走 getIndex 成功分支一次）
     *
     * @return array{0: MeilisearchKnowledgeEngine, 1: \Mockery\MockInterface}
     */
    private function makeEngine(bool $expectEnsureIndex = true): array
    {
        $index = \Mockery::mock(Indexes::class);
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('index')->with('test_chunks')->andReturn($index)->byDefault();

        if ($expectEnsureIndex) {
            $client->shouldReceive('getIndex')->with('test_chunks')->once()->andReturn($index);
        }

        $engine = new MeilisearchKnowledgeEngine($client, 'test_chunks', 'default', 3);

        return [$engine, $index];
    }

    #[Test]
    #[TestDox('容器默认绑定 database 引擎，配置切换后绑定 meilisearch 引擎')]
    public function container_binds_engine_by_config(): void
    {
        $this->assertInstanceOf(DatabaseKnowledgeEngine::class, app(KnowledgeSearchEngine::class));

        config(['knowledge.engine' => 'meilisearch']);
        $this->app->forgetInstance(KnowledgeSearchEngine::class);

        $this->assertInstanceOf(MeilisearchKnowledgeEngine::class, app(KnowledgeSearchEngine::class));
    }

    #[Test]
    #[TestDox('分块索引载荷包含向量与过滤字段')]
    public function chunk_payload_contains_vectors_and_filter_fields(): void
    {
        [$engine] = $this->makeEngine(expectEnsureIndex: false);

        $document = KnowledgeDocument::factory()->ready()->create(['title' => '苹果文档']);
        $chunk = new KnowledgeChunk([
            'document_id' => $document->id,
            'content' => '苹果是一种水果。',
            'embedding' => [1.0, 0.0, 0.0],
            'tokens' => 3,
            'sort' => 0,
        ]);
        $chunk->id = 99;

        $payload = $engine->chunkPayload($document, $chunk);

        $this->assertSame(99, $payload['id']);
        $this->assertSame($document->id, $payload['document_id']);
        $this->assertSame('苹果文档', $payload['document_title']);
        $this->assertSame('苹果是一种水果。', $payload['content']);
        $this->assertTrue($payload['is_enabled']);
        $this->assertSame('ready', $payload['status']);
        $this->assertSame([1.0, 0.0, 0.0], $payload['_vectors']['default']);
    }

    #[Test]
    #[TestDox('检索组装向量参数并按相关度映射结果')]
    public function search_maps_ranked_hits(): void
    {
        [$engine, $index] = $this->makeEngine();

        $index->shouldReceive('updateSettings')->once();
        $index->shouldReceive('rawSearch')
            ->once()
            ->withArgs(fn ($query, $params): bool => $query === null
                && $params['vector'] === [1.0, 0.0, 0.0]
                && $params['hybrid'] === ['semanticRatio' => 1.0, 'embedder' => 'default']
                && $params['filter'] === 'is_enabled = true AND status = "ready"'
                && $params['limit'] === 5
                && $params['showRankingScore'] === true)
            ->andReturn(['hits' => [
                ['id' => 7, 'document_id' => 3, 'document_title' => '苹果文档', 'content' => '苹果是一种水果。', '_rankingScore' => 0.98],
            ]]);

        $results = $engine->search('苹果', [1.0, 0.0, 0.0], 5);

        $this->assertSame([
            ['chunk_id' => 7, 'document_id' => 3, 'document_title' => '苹果文档', 'content' => '苹果是一种水果。', 'score' => 0.98],
        ], $results);
    }

    #[Test]
    #[TestDox('同步文档先按 document_id 清除旧数据再写入新分块')]
    public function sync_document_removes_stale_chunks_before_adding(): void
    {
        [$engine, $index] = $this->makeEngine();

        $document = KnowledgeDocument::factory()->ready()->create();
        $document->chunks()->create(['content' => '分块一', 'embedding' => [1.0, 0.0, 0.0], 'tokens' => 1, 'sort' => 0]);
        $document->chunks()->create(['content' => '分块二', 'embedding' => [0.0, 1.0, 0.0], 'tokens' => 1, 'sort' => 1]);

        $index->shouldReceive('updateSettings')->once();
        $index->shouldReceive('deleteDocuments')
            ->once()
            ->with(['filter' => "document_id = {$document->id}"]);
        $index->shouldReceive('addDocuments')
            ->once()
            ->withArgs(fn (array $payload): bool => count($payload) === 2 && $payload[0]['content'] === '分块一');

        $engine->syncDocument($document);
    }

    #[Test]
    #[TestDox('停用文档同步等价于移除')]
    public function sync_disabled_document_only_removes(): void
    {
        [$engine, $index] = $this->makeEngine();

        $document = KnowledgeDocument::factory()->disabled()->ready()->create();
        $document->chunks()->create(['content' => '分块', 'embedding' => [1.0, 0.0, 0.0], 'tokens' => 1, 'sort' => 0]);

        $index->shouldReceive('updateSettings')->once();
        $index->shouldReceive('deleteDocuments')
            ->once()
            ->with(['filter' => "document_id = {$document->id}"]);
        $index->shouldNotReceive('addDocuments');

        $engine->syncDocument($document);
    }
}
