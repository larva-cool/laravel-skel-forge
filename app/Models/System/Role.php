<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

/**
 * 系统角色模型
 *
 * @property int $id 角色ID
 * @property string $name 角色名称
 * @property string $display_name 角色显示名称
 * @property string $guard_name 守卫名称
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 */
class Role extends \Spatie\Permission\Models\Role {}
