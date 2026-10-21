<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => 'volc',
    'default_for_images' => 'volc',
    'default_for_audio' => 'volc',//不支持音频相关功能
    'default_for_transcription' => 'volc', //不支持转录相关功能
    'default_for_embeddings' => 'volc',
    'default_for_reranking' => 'volc', //不支持排序相关功能

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'volc' => [
            // 火山方舟属于 OpenAI 兼容服务，走 chat/completions 端点；
            // openai 驱动的 Responses API 与方舟存在兼容问题（续聊报 input.type 400）
            'driver' => 'openai-compatible',
            'url' => env('VOLC_ARK_URL', 'https://ark.cn-beijing.volces.com/api/v3'),
            'key' => env('VOLC_ARK_KEY', ''),
            'store' => false,
            // 模型配置
            'models' => [
                'text' => [
                    'default' => env('VOLC_TEXT_MODEL', 'doubao-seed-2-1-lite-260915'),
                    // 聊天页可选模型列表（id 需为火山方舟已开通的模型，未开通的模型调用会报错，可从列表移除）
                    'available' => [
                        ['id' => 'doubao-seed-2-1-lite-260915', 'name' => 'Doubao Seed 2.1 Lite'],
                        ['id' => 'doubao-seed-1-6-250615', 'name' => 'Doubao Seed 1.6'],
                        ['id' => 'doubao-seed-1-6-lite-251015', 'name' => 'Doubao Seed 1.6 Lite'],
                        ['id' => 'doubao-seed-1-6-flash-250828', 'name' => 'Doubao Seed 1.6 Flash'],
                    ],
                ],
                'image' => [
                    'default' => env('VOLC_IMAGE_MODEL', 'doubao-seedream-5-0-pro-260628'),
                ],
            ],
            // 向量模型
            'embeddings' => [
                'default' => env('VOLC_EMBEDDINGS_MODEL', 'doubao-embedding-vision-250615'),
                'dimensions' => env('VOLC_EMBEDDINGS_DIMENSIONS', 2048), // 向量维度：支持传入dimensions参数降维1024使用
            ],
        ],
    ],

];
