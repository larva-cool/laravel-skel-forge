<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 自动化运营触发类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AutomationTriggerType: string implements \JsonSerializable
{
    use HasLabel;

    case BIRTHDAY = 'birthday'; // 生日提醒
    case FESTIVAL = 'festival'; // 节日提醒
    case REGISTER_ANNIVERSARY = 'register_anniversary'; // 注册周年提醒

    /**
     * 获取类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::BIRTHDAY => '生日提醒',
            self::FESTIVAL => '节日提醒',
            self::REGISTER_ANNIVERSARY => '注册周年提醒',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::BIRTHDAY => 'danger',
            self::FESTIVAL => 'warning',
            self::REGISTER_ANNIVERSARY => 'success',
        };
    }
}
