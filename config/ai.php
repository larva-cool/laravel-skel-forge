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
    'default_for_audio' => 'volc',
    'default_for_transcription' => 'volc',
    'default_for_embeddings' => 'volc',
    'default_for_reranking' => 'volc',

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
            'driver' => 'openai-compatible',
            'url' => env('VOLC_URL', 'https://ark.cn-beijing.volces.com/api/v3'),
            'key' => env('VOLC_API_KEY', ''),
        ],
        'volc2' => [
            'driver' => 'openai-compatible',
            'url' => env('VOLC_URL2', 'https://ark.cn-beijing.volces.com/api/v3'),
            'key' => env('VOLC_API_KEY2', ''),
        ],
    ],

];
