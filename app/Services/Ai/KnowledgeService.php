<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\KnowledgeDocumentStatus;
use App\Jobs\Ai\ProcessKnowledgeDocumentJob;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Contracts\KnowledgeSearchEngine;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

/**
 * 知识库服务
 *
 * 负责知识库文档的分块、向量化索引与语义检索：
 * - 文档写入后通过 indexDocument() 完成「分块 → Embedding → 落库」，并同步至检索引擎
 * - search() 将查询文本向量化后委派给 KnowledgeSearchEngine 检索，返回 Top-N 结果
 *   （database 引擎为库内暴力余弦；meilisearch 引擎检索下沉至搜索引擎）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class KnowledgeService
{
    /** 单个分块的最大字符数（超出则硬切分） */
    public const CHUNK_MAX_LENGTH = 600;

    /** Embedding 接口单批次最大输入条数 */
    public const EMBED_BATCH_SIZE = 10;

    public function __construct(
        protected readonly KnowledgeSearchEngine $engine,
    ) {}

    /**
     * 将文本按段落聚合切分为分块
     *
     * 以空行分段，顺序合并段落至接近 CHUNK_MAX_LENGTH；
     * 单段超过上限时按上限硬切分，保证所有分块不超过最大长度。
     *
     * @return array<int, string>
     */
    public function chunkText(string $content, int $maxLength = self::CHUNK_MAX_LENGTH): array
    {
        $paragraphs = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\n\s*\n/u', $content) ?: []),
            static fn (string $line): bool => $line !== '',
        ));

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            // 单段超长：先冲刷缓冲区，再按上限硬切分
            if (mb_strlen($paragraph) > $maxLength) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }
                foreach (mb_str_split($paragraph, $maxLength) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
            if (mb_strlen($candidate) > $maxLength) {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /**
     * 批量生成文本向量
     *
     * @param  array<int, string>  $texts
     * @return array{embeddings: array<int, array<int, float>>, tokens: int}
     */
    public function embed(array $texts): array
    {
        $embeddings = [];
        $tokens = 0;

        foreach (array_chunk($texts, self::EMBED_BATCH_SIZE) as $batch) {
            $response = Embeddings::for(array_values($batch))->generate();
            foreach ($response->embeddings as $vector) {
                $embeddings[] = $vector;
            }
            $tokens += $response->tokens;
        }

        return ['embeddings' => $embeddings, 'tokens' => $tokens];
    }

    /**
     * 将文档重置为待处理并派发异步索引任务
     */
    public function queueIndexing(KnowledgeDocument $document): void
    {
        $document->forceFill([
            'status' => KnowledgeDocumentStatus::PENDING,
            'error' => null,
        ])->save();

        ProcessKnowledgeDocumentJob::dispatch($document->id);
    }

    /**
     * 对文档执行完整索引：分块 → 向量化 → 落库
     *
     * 失败后标记文档为 failed 并抛出异常（交由队列重试）。
     *
     * @throws \Throwable
     */
    public function indexDocument(KnowledgeDocument $document): void
    {
        $document->markProcessing();

        try {
            // 重建索引：清理旧分块
            $document->chunks()->delete();

            $chunks = $this->chunkText($document->content);

            if ($chunks === []) {
                $document->markReady(0, 0);
                // 清空旧分块后同步引擎（移除已失效的索引数据）
                $this->engine->syncDocument($document->refresh());

                return;
            }

            $result = $this->embed($chunks);
            // 单次向量化消耗的 tokens 按分块数均摊（网关仅返回总量）
            $tokensPerChunk = intdiv($result['tokens'], max(count($chunks), 1));

            foreach ($chunks as $index => $content) {
                $document->chunks()->create([
                    'content' => $content,
                    'embedding' => $result['embeddings'][$index],
                    'tokens' => $tokensPerChunk,
                    'sort' => $index,
                ]);
            }

            $document->markReady(count($chunks), $result['tokens']);
            $this->engine->syncDocument($document->refresh());
        } catch (\Throwable $e) {
            $document->markFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * 语义检索：返回与查询最相似的 Top-N 分块
     *
     * 仅检索「已完成索引且启用」的文档分块，由检索引擎按相关度降序返回。
     *
     * @return Collection<int, array{chunk_id: int, document_id: int, document_title: string, content: string, score: float}>
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $queryVector = $this->embed([$query])['embeddings'][0] ?? [];

        if ($queryVector === [] || $this->norm($queryVector) == 0.0) {
            return collect();
        }

        return collect($this->engine->search($query, $queryVector, max($limit, 1)));
    }

    /**
     * 同步文档在检索引擎中的可见性（启用且已就绪 → 同步，否则移除）
     *
     * 用于启用状态切换、标题等元数据变更后的索引刷新。
     */
    public function syncVisibility(KnowledgeDocument $document): void
    {
        if ($document->is_enabled && $document->status === KnowledgeDocumentStatus::READY) {
            $this->engine->syncDocument($document);
        } else {
            $this->engine->removeDocument($document->id);
        }
    }

    /**
     * 从检索引擎移除文档（文档删除前调用）
     */
    public function removeFromEngine(int $documentId): void
    {
        $this->engine->removeDocument($documentId);
    }

    /**
     * 向量模长（用于零向量守卫）
     *
     * @param  array<int, float>  $vector
     */
    protected function norm(array $vector): float
    {
        return sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
    }
}
