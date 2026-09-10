<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Task;

use App\Enums\Traits\HasLabel;

/**
 * 任务组类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum TaskGroupType: int implements \JsonSerializable
{
    use HasLabel;

    case NEWBIE = 0; // 新手任务（一次性）
    case DAILY = 1; // 每日任务（每日重置）
    case WEEKLY = 2; // 每周任务（每周重置）
    case SIGN_IN = 3; // 签到任务（含每日签到+连续签到阶梯）
    case CUSTOM = 9; // 自定义任务组

    /**
     * 获取任务组类型的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::NEWBIE => '新手任务',
            self::DAILY => '每日任务',
            self::WEEKLY => '每周任务',
            self::SIGN_IN => '签到任务',
            self::CUSTOM => '自定义',
        };
    }

    /**
     * 获取对应默认的重置类型
     */
    public function defaultResetType(): TaskResetType
    {
        return match ($this) {
            self::NEWBIE => TaskResetType::NONE,
            self::DAILY, self::SIGN_IN => TaskResetType::DAILY,
            self::WEEKLY => TaskResetType::WEEKLY,
            self::CUSTOM => TaskResetType::NONE,
        };
    }
}
