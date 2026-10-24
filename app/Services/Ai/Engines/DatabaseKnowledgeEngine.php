<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Ai\Engines;

use App\Models\Ai\KnowledgeChunk;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Contracts\KnowledgeSearchEngine;

/**
 * 数据库检索引擎（默认）。
 *
 * 全量拉取已启用文档的分块做暴力余弦相似度计算。零外部依赖，
 * 适用于开发/测试与小规模知识库；分块量大时应切换至 Meilisearch 引擎。
 * 数据库即检索源，同步/移除操作为空实现。
 */
class DatabaseKnowledgeEngine implements KnowledgeSearchEngine
{
    /**
     * 语义检索：按余弦相似度降序返回 Top-N 分块。
     *
     * @return array<int, array{chunk_id: int, document_id: int, document_title: string, content: string, score: float}>
     */
    public function search(string $query, array $queryVector, int $limit): array
    {
        $queryNorm = $this->norm($queryVector);
        if ($queryNorm == 0.0) {
            return [];
        }

        return KnowledgeChunk::query()
            ->select(['knowledge_chunks.id', 'knowledge_chunks.document_id', 'knowledge_chunks.content', 'knowledge_chunks.embedding', 'knowledge_documents.title'])
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.document_id')
            ->where('knowledge_documents.is_enabled', true)
            ->where('knowledge_documents.status', 'ready')
            ->get()
            ->map(fn (KnowledgeChunk $chunk): array => [
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->document_id,
                'document_title' => $chunk->getAttribute('title'),
                'content' => $chunk->content,
                'score' => $this->cosineSimilarity($queryVector, $queryNorm, $chunk->embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * 数据库即检索源，无需同步。
     */
    public function syncDocument(KnowledgeDocument $document): void
    {
        // 空实现：database 引擎直接查询 knowledge_chunks 表
    }

    /**
     * 数据库即检索源，无需移除（分块随文档级联删除）。
     */
    public function removeDocument(int $documentId): void
    {
        // 空实现：knowledge_chunks.document_id 有级联删除外键
    }

    /**
     * 计算两个向量的余弦相似度
     *
     * @param  array<int, float>  $query  查询向量
     * @param  float  $queryNorm  查询向量模长
     * @param  array<int, float>  $vector  待比较向量
     */
    protected function cosineSimilarity(array $query, float $queryNorm, array $vector): float
    {
        $norm = $this->norm($vector);
        if ($norm == 0.0) {
            return 0.0;
        }

        $dot = 0.0;
        foreach ($query as $i => $value) {
            $dot += $value * ($vector[$i] ?? 0.0);
        }

        return $dot / ($queryNorm * $norm);
    }

    /**
     * 向量模长
     *
     * @param  array<int, float>  $vector
     */
    protected function norm(array $vector): float
    {
        return sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
    }
}
