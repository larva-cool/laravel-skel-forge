<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 操作审计日志模型工厂
 *
 * @extends Factory<AuditLog>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => null,
            'operator_name' => fake()->userName(),
            'route' => fake()->randomElement(['admin.users.update', 'admin.roles.store', 'admin.settings.batch', 'admin.menus.destroy']),
            'method' => fake()->randomElement(['POST', 'PUT', 'DELETE']),
            'uri' => 'admin/'.fake()->randomElement(['users/1', 'roles', 'settings/batch', 'menus/2']),
            'title' => fake()->randomElement(['编辑用户', '新增角色', '保存系统设置', '删除菜单']),
            'payload' => ['name' => fake()->name()],
            'status_code' => 200,
            'runtime' => fake()->randomFloat(3, 0.001, 3),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }

    /**
     * 失败状态
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => 422,
            'error' => '给定的数据无效。',
        ]);
    }
}
