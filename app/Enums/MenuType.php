<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 后台菜单类型枚举
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
enum MenuType: int implements \JsonSerializable
{
    use HasLabel;

    case DIRECTORY = 0; // 目录
    case MENU = 1; // 菜单
    case BUTTON = 2; // 按钮/权限
    case IFRAME = 3; // iframe 内嵌
    case LINK = 4; // 外链（新窗口打开）

    /**
     * 获取菜单类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DIRECTORY => '目录',
            self::MENU => '菜单',
            self::BUTTON => '按钮',
            self::IFRAME => '内嵌',
            self::LINK => '外链',
        };
    }

    /**
     * 是否为叶子节点类型（菜单 / 按钮 / iframe / 外链）
     */
    public function isLeaf(): bool
    {
        return $this !== self::DIRECTORY;
    }

    /**
     * 是否在侧边栏显示为可导航项（非按钮）
     */
    public function isNavigable(): bool
    {
        return $this !== self::BUTTON;
    }
}
