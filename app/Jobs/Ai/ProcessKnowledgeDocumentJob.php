<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\KnowledgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 知识库文档索引任务
 *
 * 异步执行文档「分块 → 向量化 → 落库」，失败由 KnowledgeService 标记文档状态，
 * 达到最大重试次数后通过 failed() 记录最终失败。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** 单次任务最大执行时间（5 分钟） */
    public int $timeout = 300;

    public function __construct(
        public readonly int $documentId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(KnowledgeService $service): void
    {
        $document = KnowledgeDocument::query()->find($this->documentId);
        if (! $document) {
            Log::warning('知识库文档不存在', ['document_id' => $this->documentId]);

            return;
        }

        // 幂等：处理中的文档不重复执行（防止同一文档重复派发）
        if ($document->status->value === 'processing') {
            return;
        }

        $service->indexDocument($document);
    }

    /**
     * 任务最终失败回调（达到最大重试后）
     */
    public function failed(Throwable $e): void
    {
        Log::error('知识库文档索引最终失败', [
            'document_id' => $this->documentId,
            'error' => $e->getMessage(),
        ]);
    }
}
