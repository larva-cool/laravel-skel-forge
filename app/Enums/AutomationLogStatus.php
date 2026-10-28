<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 自动化运营触发日志状态枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AutomationLogStatus: string implements \JsonSerializable
{
    use HasLabel;

    case SUCCESS = 'success'; // 成功
    case FAILED = 'failed'; // 失败

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => '成功',
            self::FAILED => '失败',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
        };
    }
}
