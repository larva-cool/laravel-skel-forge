<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Models\Admin\Admin;

/**
 * 需要权限校验的 AI 工具契约。
 *
 * 实现该契约的工具会在 Assistant::tools() 装配阶段按当前管理员的权限点过滤，
 * 无权限的工具不会暴露给模型；handle() 执行时通过 authorizeAdmin() 再做一次
 * 兜底校验，防止模型被诱导发起越权调用。
 */
interface RequiresPermission
{
    /**
     * 执行该工具所需的权限点（任一满足即可）；返回 null 或空数组表示不限制
     *
     * @return string|array<int, string>|null
     */
    public function permission(): string|array|null;

    /**
     * 注入当前操作的管理员（由 Agent 装配工具时调用）
     */
    public function forAdmin(?Admin $admin): static;
}
