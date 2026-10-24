<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Contracts\KnowledgeSearchEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * 知识库检索索引同步命令
 *
 * 将数据库中「已完成索引且启用」的文档分块全量推送至当前检索引擎。
 * 用于切换到 Meilisearch 引擎后的存量数据迁移，或索引损坏后的重建；
 * 不重做 Embedding，直接复用 knowledge_chunks 表中的向量。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Signature('knowledge:sync-index')]
#[Description('将已就绪且启用的知识库文档分块同步至当前检索引擎（存量迁移/索引重建）')]
class KnowledgeSyncIndexCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KnowledgeSearchEngine $engine): int
    {
        $engineName = class_basename($engine);
        $this->info("当前检索引擎：{$engineName}");

        $query = KnowledgeDocument::query()
            ->where('status', 'ready')
            ->where('is_enabled', true);

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('没有可同步的文档（status=ready 且 is_enabled=true）。');

            return self::SUCCESS;
        }

        $this->info("开始同步 {$total} 个文档的分块...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $query->chunkById(50, function ($documents) use ($bar, $engine, &$synced): void {
            foreach ($documents as $document) {
                $engine->syncDocument($document);
                $synced++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("同步完成：共 {$synced} 个文档。");

        return self::SUCCESS;
    }
}
