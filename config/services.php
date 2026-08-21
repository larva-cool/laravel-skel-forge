<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 微信公众号
    |--------------------------------------------------------------------------
    |
    | 由 App\Services\WechatService 消费，基于 w7corp/easywechat 的
    | OfficialAccount 应用。aes_key 仅在消息加解密（兼容/安全模式）下必填。
    |
    */

    'wechat' => [
        'app_id' => env('WECHAT_APP_ID'),
        'app_secret' => env('WECHAT_APP_SECRET'),
        'token' => env('WECHAT_TOKEN'),
        'aes_key' => env('WECHAT_AES_KEY'),
        'use_stable_access_token' => env('WECHAT_USE_STABLE_ACCESS_TOKEN', true),
        'base_uri' => env('WECHAT_BASE_URI', 'https://api.weixin.qq.com/'),
        'oauth_redirect_url' => env('WECHAT_OAUTH_REDIRECT_URL', '/wechat/auth-callback'),
        'http_timeout' => env('WECHAT_HTTP_TIMEOUT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | 微信小程序
    |--------------------------------------------------------------------------
    |
    | 由 App\Services\WechatMiniService 消费，基于 w7corp/easywechat 的
    | MiniApp 应用。
    |
    */

    'wechat_mini' => [
        'app_id' => env('WECHAT_MINI_APP_ID'),
        'app_secret' => env('WECHAT_MINI_APP_SECRET'),
        'token' => env('WECHAT_MINI_TOKEN'),
        'aes_key' => env('WECHAT_MINI_AES_KEY'),
        'use_stable_access_token' => env('WECHAT_MINI_USE_STABLE_ACCESS_TOKEN', true),
        'base_uri' => env('WECHAT_MINI_BASE_URI', 'https://api.weixin.qq.com/'),
        'http_timeout' => env('WECHAT_MINI_HTTP_TIMEOUT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | 苹果账号登录（Sign in with Apple）
    |--------------------------------------------------------------------------
    |
    | 由 App\Http\Requests\Api\V1\Auth\AppleLoginRequest 消费，用于向苹果
    | 换取 id_token。client_id 为 App 的 Bundle ID（原生端）或 Services ID
    | （Web 端）；client_secret 是使用 Apple 私钥签发的 JWT，有效期最长 6 个月，
    | 需定期轮换。
    |
    */

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
    ],

];
