<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Ai\KnowledgeDocument;

/**
 * 知识库检索引擎契约。
 *
 * 抽象分块的「同步/移除/检索」三个动作，KnowledgeService 面向该契约编程：
 * - database 引擎：直接查库做暴力余弦，零外部依赖
 * - meilisearch 引擎：分块向量同步至 Meilisearch，检索下沉到引擎执行
 */
interface KnowledgeSearchEngine
{
    /**
     * 语义检索：返回按相关度降序的分块
     *
     * @param  array<int, float>  $queryVector  查询向量（由 KnowledgeService 生成）
     * @return array<int, array{chunk_id: int, document_id: int, document_title: string, content: string, score: float}>
     */
    public function search(string $query, array $queryVector, int $limit): array;

    /**
     * 将文档的全部分块同步至检索引擎（含向量与过滤字段）
     *
     * 文档未启用或未就绪时应等价于移除（保证引擎内数据始终可检索）。
     */
    public function syncDocument(KnowledgeDocument $document): void;

    /**
     * 从检索引擎移除文档的全部分块
     */
    public function removeDocument(int $documentId): void;
}
