<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Concerns;

use App\Models\Admin\Admin;

/**
 * AI 工具的管理员权限授权能力。
 *
 * 配合 App\Ai\Contracts\RequiresPermission 契约使用：Assistant 装配工具时
 * 注入当前管理员并按权限过滤；handle() 内通过 authorizeAdmin() 做执行兜底。
 */
trait AuthorizesAdmin
{
    /**
     * 当前操作的管理员（由 Agent 装配工具时注入）
     */
    protected ?Admin $admin = null;

    /**
     * 执行该工具所需的权限点（由使用类实现）
     *
     * @return string|array<int, string>|null
     */
    abstract public function permission(): string|array|null;

    /**
     * 注入当前操作的管理员
     */
    public function forAdmin(?Admin $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    /**
     * 校验当前管理员是否具备执行权限，未通过时返回拒绝文案
     */
    protected function authorizeAdmin(): ?string
    {
        $permission = $this->permission();

        if ($permission === null || $permission === []) {
            return null;
        }

        if (! $this->admin instanceof Admin) {
            return '无法识别当前操作者身份，出于安全考虑该操作已被拒绝。';
        }

        if (! $this->admin->canAny((array) $permission)) {
            return '您当前的角色没有执行此操作所需的权限，操作已被拒绝。如需开通，请联系超级管理员。';
        }

        return null;
    }
}
