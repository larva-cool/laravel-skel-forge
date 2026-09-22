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
            'driver' => 'openai',
            'url' => env('VOLC_URL', 'https://ark.cn-beijing.volces.com/api/v3'),
            'key' => env('VOLC_API_KEY', ''),
            'store' => false,
            // 模型配置
            'models' => [
                'text' => [
                    'default' => env('VOLC_TEXT_MODEL', 'doubao-seed-2-0-lite-260428'),
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
