<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 钱包流水状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum WalletTradeStatus: string implements \JsonSerializable
{
    use HasLabel;

    case PENDING = 'pending'; // 处理中
    case SUCCESS = 'success'; // 已完成
    case FAILED = 'failed'; // 已失败

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '处理中',
            self::SUCCESS => '已完成',
            self::FAILED => '已失败',
        };
    }

    /**
     * 获取 Element Plus Tag 类型
     */
    public function labelType(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
        };
    }

    /**
     * 是否已完成
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }
}
