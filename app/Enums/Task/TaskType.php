<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums\Task;

use App\Enums\Traits\HasLabel;

/**
 * 任务类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum TaskType: string implements \JsonSerializable
{
    use HasLabel;

    case SIGN_IN = 'sign_in'; // 签到
    case COMPLETE_PROFILE = 'complete_profile'; // 完善资料
    case FOLLOW_OFFICIAL_ACCOUNT = 'follow_official_account'; // 关注公众号
    case INVITE_USER = 'invite_user'; // 邀请好友
    case DAILY_LOGIN = 'daily_login'; // 每日登录
    case CUSTOM_ACTION = 'custom_action'; // 自定义行为

    /**
     * 获取任务类型的可读名称
     */
    public function label(): string
    {
        return match ($this) {
            self::SIGN_IN => '签到',
            self::COMPLETE_PROFILE => '完善资料',
            self::FOLLOW_OFFICIAL_ACCOUNT => '关注公众号',
            self::INVITE_USER => '邀请好友',
            self::DAILY_LOGIN => '每日登录',
            self::CUSTOM_ACTION => '自定义行为',
        };
    }
}
