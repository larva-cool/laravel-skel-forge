<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 通知严重级别枚举（全局共享）
 *
 * 供管理员域与用户域通知共同使用，用于通知中心展示与前端图标/颜色选择。
 * 当前 value 仅作语义标识，不参与业务逻辑。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum NotificationLevel: string implements \JsonSerializable
{
    use HasLabel;

    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::INFO => '提示',
            self::SUCCESS => '成功',
            self::WARNING => '警告',
            self::ERROR => '错误',
        };
    }
}
