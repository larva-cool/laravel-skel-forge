<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Contracts\KnowledgeSearchEngine;
use App\Services\Ai\Engines\DatabaseKnowledgeEngine;
use App\Services\Ai\Engines\MeilisearchKnowledgeEngine;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;

/**
 * 知识库服务提供者
 *
 * 按 knowledge.engine 配置注册检索引擎：database（库内暴力余弦，零外部依赖）
 * 或 meilisearch（向量检索下沉至 Meilisearch）。引擎无状态，可安全单例复用。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class KnowledgeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KnowledgeSearchEngine::class, function () {
            return match ((string) config('knowledge.engine', 'database')) {
                'meilisearch' => new MeilisearchKnowledgeEngine(
                    client: new Client(
                        (string) config('scout.meilisearch.host'),
                        config('scout.meilisearch.key'),
                    ),
                    indexUid: (string) config('knowledge.meilisearch.index', 'knowledge_chunks'),
                    embedder: (string) config('knowledge.meilisearch.embedder', 'default'),
                    dimensions: (int) config('ai.providers.volc.models.embeddings.dimensions', 2048),
                ),
                default => new DatabaseKnowledgeEngine,
            };
        });
    }
}
