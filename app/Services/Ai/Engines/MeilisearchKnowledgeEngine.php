<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Ai\Engines;

use App\Models\Ai\KnowledgeChunk;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Contracts\KnowledgeSearchEngine;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;

/**
 * Meilisearch 检索引擎。
 *
 * 分块（含向量与过滤字段）同步至 Meilisearch 索引，检索在引擎内完成，
 * 避免数据库暴力余弦的 O(N) 内存/算力开销。向量由应用侧生成（userProvided
 * embedder），本实现为纯向量检索（semanticRatio=1.0）。
 */
class MeilisearchKnowledgeEngine implements KnowledgeSearchEngine
{
    /** 索引结构是否已在当前进程确认 */
    protected bool $indexReady = false;

    public function __construct(
        protected readonly Client $client,
        protected readonly string $indexUid,
        protected readonly string $embedder,
        protected readonly int $dimensions,
    ) {}

    /**
     * 语义检索：向量召回 + 启用/就绪过滤，按相关度降序返回 Top-N。
     *
     * @return array<int, array{chunk_id: int, document_id: int, document_title: string, content: string, score: float}>
     */
    public function search(string $query, array $queryVector, int $limit): array
    {
        $this->ensureIndex();

        $results = $this->index()->rawSearch(null, [
            'vector' => $queryVector,
            'hybrid' => ['semanticRatio' => 1.0, 'embedder' => $this->embedder],
            'filter' => 'is_enabled = true AND status = "ready"',
            'limit' => $limit,
            'showRankingScore' => true,
        ]);

        return array_map(static fn (array $hit): array => [
            'chunk_id' => (int) $hit['id'],
            'document_id' => (int) $hit['document_id'],
            'document_title' => (string) $hit['document_title'],
            'content' => (string) $hit['content'],
            'score' => (float) ($hit['_rankingScore'] ?? 0.0),
        ], $results['hits'] ?? []);
    }

    /**
     * 同步文档分块至索引：先按 document_id 清除旧数据（覆盖重建产生的旧分块 ID），
     * 再写入当前分块；未启用或未就绪的文档等价于移除。
     */
    public function syncDocument(KnowledgeDocument $document): void
    {
        $this->removeDocument($document->id);

        if (! $document->is_enabled || $document->status->value !== 'ready') {
            return;
        }

        $payload = $document->chunks()->get()
            ->map(fn (KnowledgeChunk $chunk): array => $this->chunkPayload($document, $chunk))
            ->all();

        if ($payload !== []) {
            $this->index()->addDocuments($payload);
        }
    }

    /**
     * 按 document_id 过滤删除文档的全部分块。
     */
    public function removeDocument(int $documentId): void
    {
        $this->ensureIndex();

        $this->index()->deleteDocuments(['filter' => "document_id = {$documentId}"]);
    }

    /**
     * 分块索引载荷（公开供单元测试断言结构）。
     *
     * @return array<string, mixed>
     */
    public function chunkPayload(KnowledgeDocument $document, KnowledgeChunk $chunk): array
    {
        return [
            'id' => $chunk->id,
            'document_id' => $document->id,
            'document_title' => $document->title,
            'content' => $chunk->content,
            'is_enabled' => $document->is_enabled,
            'status' => $document->status->value,
            // embedding 经 JSON 落库后浮点可能退化为整数，统一规范为 float
            '_vectors' => [$this->embedder => array_map('floatval', $chunk->embedding ?? [])],
        ];
    }

    /**
     * 确认索引存在且设置就绪（每进程一次，设置更新为幂等操作）。
     */
    protected function ensureIndex(): void
    {
        if ($this->indexReady) {
            return;
        }

        try {
            $this->client->getIndex($this->indexUid);
        } catch (ApiException) {
            $this->client->createIndex($this->indexUid, ['primaryKey' => 'id']);
        }

        $this->index()->updateSettings([
            'searchableAttributes' => ['content'],
            'filterableAttributes' => ['document_id', 'is_enabled', 'status'],
            'embedders' => [
                $this->embedder => [
                    'source' => 'userProvided',
                    'dimensions' => $this->dimensions,
                ],
            ],
        ]);

        $this->indexReady = true;
    }

    /**
     * 获取索引端点。
     */
    protected function index(): Indexes
    {
        return $this->client->index($this->indexUid);
    }
}
