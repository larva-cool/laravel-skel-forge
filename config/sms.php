<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

return [
    // HTTP 请求的超时时间（秒）
    'timeout' => 5.0,

    /*
    |--------------------------------------------------------------------------
    | Default Setting
    |--------------------------------------------------------------------------
    |
    | This option defines the default sms gateway that gets used when writing
    | messages to the sms. The name specified in this option should match
    | one of the gateways defined in the "gateways" configuration array.
    |
    */
    'default' => [
        // 网关调用策略，默认：顺序调用
        'strategy' => \Overtrue\EasySms\Strategies\OrderStrategy::class,

        /*
        |--------------------------------------------------------------------------
        | Default Gateways
        |--------------------------------------------------------------------------
        |
        | This option defines the default sms gateway that gets used when writing
        | messages to the sms. The name specified in this option should match
        | one of the gateways defined in the "gateways" configuration array.
        |
        */
        'gateways' => [
            'volcengine', 'aliyun'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure the sms gateways for your application.  This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Gateways: "aliyun", "alidayu", "yunpian", "submail",
    |                    "errorlog", "luosimao", "yuntongxun", "huyi"
    |                    "juhe", "sendcloud", "baidu", "huaxin", "chuanglan"
    |                    "rongcloud", "tianyiwuxian", "twilio", "qcloud", "avatardata"
    | 更多配置 https://github.com/overtrue/easy-sms
    |
    */
    'gateways' => [
        'aliyun' => [
            'access_key_id' => '',
            'access_key_secret' => '',
            'sign_name' => '',
        ],
        'volcengine' => [
            'access_key_id' => env('VOLC_ACCESS_KEY'), // 平台分配给用户的access_key_id
            'access_key_secret' => env('VOLC_SECRET_KEY'), // 平台分配给用户的access_key_secret
            'region_id' => 'cn-north-1', // 国内节点 cn-north-1，国外节点 ap-singapore-1，不填或填错，默认使用国内节点
            'sign_name' => '', // 平台上申请的接口短信签名或者签名ID，可不填，发送短信时data中指定
            'sms_account' => '', // 消息组帐号,火山短信页面右上角，短信应用括号中的字符串，可不填，发送短信时data中指定
        ],
    ],
];
