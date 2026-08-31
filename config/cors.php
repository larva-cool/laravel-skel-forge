<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 要应用 CORS 配置的路径
    // '* 表示允许所有路径， 表示不允许任何路径
    // 注意：如果使用正则表达式，需要在模式中添加 'i' 标志，以忽略大小写匹配
    'paths' => ['api/*', 'admin/*', 'sanctum/csrf-cookie'],

    // 允许的请求方法
    // '* 表示允许所有请求方法， 表示不允许任何请求方法
    'allowed_methods' => ['*'],

    // 允许的请求源
    // '* 表示允许所有请求源， 表示不允许任何请求源
    // 注意：如果使用正则表达式，需要在模式中添加 'i' 标志，以忽略大小写匹配
    'allowed_origins' => ['*'],

    // 允许的请求源模式
    // '* 表示允许所有请求源， 表示不允许任何请求源
    // 注意：如果使用正则表达式，需要在模式中添加 'i' 标志，以忽略大小写匹配
    'allowed_origins_patterns' => ['/^http?:\/\/localhost(:\d+)?$/i'],

    // 允许的请求头
    // '* 表示允许所有请求头， 表示不允许任何请求头
    'allowed_headers' => [
        '*'
    ],

    // 允许的响应头
    // '* 表示允许所有响应头， 表示不允许任何响应头
    'exposed_headers' => [

    ],

    // 最大缓存时间
    // 单位：秒
    'max_age' => 86400,

    // 是否支持凭证
    // true 表示支持凭证， false 表示不支持凭证
    // 注意：如果支持凭证，客户端需要在请求头中添加 'withCredentials' 字段
    'supports_credentials' => false,

];
