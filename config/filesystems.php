<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'tos' => [
            'driver' => 'tos',
            'access_key' => env('VOLC_ACCESS_KEY'),
            'access_secret' => env('VOLC_SECRET_KEY'),
            'region' => env('TOS_REGION'),
            'bucket' => env('TOS_BUCKET'),
            'endpoint' => env('TOS_ENDPOINT'),
            'url' => env('TOS_URL'),
            'is_custom_domain' => false,
            'options' => [
                'max_age' => '31536000'
            ],
            'ssl' => true,
            'throw' => false,
            'report' => false,
        ],

        'cos' => [
            'driver' => 'cos',
            // 'endpoint' => env('TENCENT_COS_ENDPOINT'), // 接入点，留空即可
            'region' => env('TENCENT_COS_REGION'), // 存储桶所在区域，如 ap-guangzhou
            'credentials' => [ // 认证凭证
                'appId' => env('TENCENT_COS_APP_ID'), // 存储桶名称后缀，如 1258464748
                'secretId' => env('TENCENT_COS_SECRET_ID'),
                'secretKey' => env('TENCENT_COS_SECRET_KEY'),
                'token' => env('TENCENT_COS_TOKEN'), // 临时密钥时使用，可选
            ],
            'bucket' => env('TENCENT_COS_BUCKET'), // 存储桶名称
            'root' => env('COS_PREFIX'), // 存储路径前缀，可选
            'url' => env('TENCENT_COS_URL'), // CDN 加速域名，可选
            // 以下均可省略
            'scheme' => 'https', // 协议头，默认 http
            'timeout' => 3600,
            'connect_timeout' => 3600,
            'ip' => null,
            'port' => null,
            'domain' => null,
            'proxy' => null,
            'encrypt' => null,
        ],

        'oss' => [
            'driver'        => 'oss',
            'access_id'     => env('OSS_ACCESS_ID', 'your access id'),
            'access_key'    => env('OSS_ACCESS_KEY', 'your access key'),
            'bucket'        => env('OSS_BUCKET', 'your bucket'),
            'endpoint'      => env('OSS_ENDPOINT', 'your endpoint'),  // 不要使用 CNAME，请使用 OSS 的 endpoint 地址
            'url'           => env('OSS_URL', 'cdn url'),             // 自定义访问域名，可以是 CDN 或绑定的域名，如 https://www.bbb.com，末尾不要斜杠
            'root'          => env('OSS_ROOT', ''),                   // 文件路径前缀，若所有内容存放在子目录中则填写，否则留空
            'visibility'    => 'public',                               // 默认可见性，可选：public / private
            'security_token' => null,                                  // STS 临时安全令牌，用于临时授权场景
            'proxy'         => null,                                   // HTTP 代理地址
            'timeout'       => 3600,                                   // 请求超时时间（秒）
            'ssl'           => true,                                   // 是否使用 HTTPS
        ],

        'kodo' => [
            'driver'            => 'kodo',
            'access_key'        => env('QINIU_ACCESS_KEY'),
            'secret_key'        => env('QINIU_SECRET_KEY'),
            'bucket'            => env('QINIU_BUCKET'),
            'url'               => env('QINIU_BUCKET_URL'), // CDN 或自定义域名，末尾不要斜杠，如 https://cdn.example.com
            'root'              => env('QINIU_ROOT', ''), // 存储路径前缀，可选
            'is_custom_domain'  => false, // 如果 endpoint 是绑定的自定义域名，设置为 true，同时 url 设置无效
            'endpoint'          => env('QINIU_ENDPOINT', ''), // 自定义域名（当 is_custom_domain 为 true 时使用）
            'ssl'               => true, // 是否使用 HTTPS
            'upload_url'        => env('QINIU_UPLOAD_URL', 'https://upload.qiniup.com'), // 上传端点，可选
            'visibility'        => 'public', // 默认文件可见性：public 或 private
            'directory_visibility' => 'public', // 默认目录可见性：public 或 private，可选
            'options'           => [], // 传递给底层 Kodo 适配器的额外选项，可选
            'throw'             => false,
            'report'            => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
