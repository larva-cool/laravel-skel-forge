<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 字典标签样式枚举
 *
 * 对应 Element Plus 的 tag type
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum DictLabelType: string implements \JsonSerializable
{
    use HasLabel;

    case DEFAULT = 'default'; // 默认
    case PRIMARY = 'primary'; // 主要
    case SUCCESS = 'success'; // 成功
    case WARNING = 'warning'; // 警告
    case DANGER = 'danger'; // 危险
    case INFO = 'info'; // 信息

    /**
     * 获取标签样式标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => '默认',
            self::PRIMARY => '主要',
            self::SUCCESS => '成功',
            self::WARNING => '警告',
            self::DANGER => '危险',
            self::INFO => '信息',
        };
    }
}
