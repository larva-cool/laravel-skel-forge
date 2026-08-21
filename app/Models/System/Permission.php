<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use Illuminate\Support\Collection;

/**
 * 系统权限模型
 *
 * @property int $id 权限 ID
 * @property string $name 权限名称
 * @property string $display_name 权限显示名称
 * @property string $guard_name 守卫名称
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 */
class Permission extends \Spatie\Permission\Models\Permission
{
    /**
     * 按 guard 名称过滤权限 ID，返回合法的权限 ID 数组
     *
     * @param  array<int, int>  $permissionIds
     * @return array<int, int>
     */
    public static function filterByGuard(array $permissionIds, string $guardName): array
    {
        if (empty($permissionIds)) {
            return [];
        }

        return static::query()
            ->whereIn('id', $permissionIds)
            ->where('guard_name', $guardName)
            ->pluck('id')
            ->toArray();
    }

    /**
     * 按 guard 名称过滤权限 ID，返回合法的权限 ID 集合
     *
     * @param  array<int, int>  $permissionIds
     */
    public static function collectByGuard(array $permissionIds, string $guardName): Collection
    {
        if (empty($permissionIds)) {
            return collect();
        }

        return static::query()
            ->whereIn('id', $permissionIds)
            ->where('guard_name', $guardName)
            ->get(['id', 'name', 'guard_name']);
    }
}
