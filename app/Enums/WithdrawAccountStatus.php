<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 提现收款账户状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum WithdrawAccountStatus: string implements \JsonSerializable
{
    use HasLabel;

    case ACTIVE = 'active'; // 启用
    case DISABLED = 'disabled'; // 已禁用

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '启用',
            self::DISABLED => '已禁用',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::DISABLED => 'danger',
        };
    }

    /**
     * 是否启用
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
