<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 实名认证类型枚举
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum IdentityType: string implements \JsonSerializable
{
    use HasLabel;

    case PERSONAL = 'personal'; // 个人
    case ENTERPRISE = 'enterprise'; // 企业

    /**
     * 获取类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => '个人',
            self::ENTERPRISE => '企业',
        };
    }

    /**
     * 是否为个人认证
     */
    public function isPersonal(): bool
    {
        return $this === self::PERSONAL;
    }

    /**
     * 是否为企业认证
     */
    public function isEnterprise(): bool
    {
        return $this === self::ENTERPRISE;
    }
}
