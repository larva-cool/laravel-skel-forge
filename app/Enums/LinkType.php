<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * Banner 链接类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum LinkType: string implements \JsonSerializable
{
    use HasLabel;

    case NONE = 'none'; // 无链接
    case URL = 'url'; // 外部 URL
    case PAGE = 'page'; // 内部页面路由
    case GOODS = 'goods'; // 跳转商品
    case WEBVIEW = 'webview'; // WebView 内嵌页

    /**
     * 获取链接类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => '无链接',
            self::URL => '外部链接',
            self::PAGE => '内部页面',
            self::GOODS => '跳转商品',
            self::WEBVIEW => '内嵌网页',
        };
    }
}
