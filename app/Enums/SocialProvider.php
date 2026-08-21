<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 社交账号提供商枚举
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
enum SocialProvider: string implements \JsonSerializable
{
    use HasLabel;

    case WECHAT_MP = 'wechat_mp';
    case WECHAT_APP = 'wechat_app';
    case WECHAT_MINI_PROGRAM = 'wechat_mini_program';
    case APPLE = 'apple';
    case DOUYIN = 'douyin';
    case KUAISHOU = 'kuaishou';
    case XIAOHONGSHU = 'xiaohongshu';

    /**
     * 获取社交账号提供商的标签
     */
    public function label(): string
    {
        return match ($this) {
            self::WECHAT_MP => '微信公众号',
            self::WECHAT_APP => '微信应用',
            self::WECHAT_MINI_PROGRAM => '微信小程序',
            self::APPLE => 'Apple ID',
            self::DOUYIN => '抖音',
            self::KUAISHOU => '快手',
            self::XIAOHONGSHU => '小红书',
        };
    }
}
