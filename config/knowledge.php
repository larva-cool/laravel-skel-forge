<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 知识库检索引擎
    |--------------------------------------------------------------------------
    |
    | database：在数据库内做暴力余弦计算，零外部依赖，适合开发/测试与小规模数据。
    | meilisearch：分块向量同步至 Meilisearch，检索在引擎内完成，支撑大规模分块。
    |
    */

    'engine' => env('KNOWLEDGE_ENGINE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Meilisearch 引擎
    |--------------------------------------------------------------------------
    |
    | 连接信息复用 config/scout.php 的 meilisearch 节（MEILISEARCH_HOST/KEY）。
    | embedder 采用 userProvided：向量由应用侧（默认火山方舟 Embedding）生成，
    | dimensions 需与 ai.providers.volc.models.embeddings.dimensions 保持一致。
    |
    */

    'meilisearch' => [
        'index' => env('KNOWLEDGE_MEILI_INDEX', 'knowledge_chunks'),
        'embedder' => 'default',
    ],
];
