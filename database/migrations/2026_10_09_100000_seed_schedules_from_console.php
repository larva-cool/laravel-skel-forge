<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\ScheduleTaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 将 console.php 中的静态定时任务迁移到 schedules 表。
     */
    public function up(): void
    {
        $now = now();

        $tasks = [
            [
                'name' => '清理过期密码重置令牌',
                'command' => 'auth:clear-resets',
                'expression' => '0 1 * * *',
                'description' => '每天凌晨 1:00 清理过期的密码重置令牌',
                'on_one_server' => true,
            ],
            [
                'name' => '清理过期模型实例',
                'command' => 'model:prune',
                'expression' => '5 1 * * *',
                'description' => '每天凌晨 1:05 清理过期的模型实例',
                'on_one_server' => true,
            ],
            [
                'name' => '统计用户数据',
                'command' => 'stats:user',
                'expression' => '10 1 * * *',
                'description' => '每天凌晨 1:10 统计昨天的用户数据（总数、新增、活跃）',
                'on_one_server' => true,
            ],
            [
                'name' => '同步规则分群成员',
                'command' => 'segments:sync-rules',
                'expression' => '0 2 * * *',
                'description' => '每天凌晨 2:00 同步所有启用的规则分群成员',
                'on_one_server' => true,
            ],
            [
                'name' => '清理 Telescope 记录（保留异常）',
                'command' => 'telescope:prune',
                'expression' => '15 1 * * *',
                'description' => '每天凌晨 1:15 清理 48 小时前的 Telescope 记录，保留异常数据',
                'options' => json_encode([['name' => 'keep-exceptions']]),
                'options_with_value' => json_encode([['name' => 'hours', 'value' => '48']]),
                'on_one_server' => true,
            ],
            [
                'name' => 'Horizon 状态快照',
                'command' => 'horizon:snapshot',
                'expression' => '*/5 * * * *',
                'description' => '每 5 分钟执行一次 Horizon snapshot，记录数据库、缓存、队列等状态',
                'on_one_server' => true,
            ],
            [
                'name' => '关闭超时充值订单',
                'command' => 'wallet:close-expired-recharges',
                'expression' => '*/5 * * * *',
                'description' => '每 5 分钟关闭超时未支付的充值订单（通过 Job 调度）',
                'options_with_value' => json_encode([['name' => 'limit', 'value' => '500']]),
                'on_one_server' => true,
            ],
            [
                'name' => '清理 Telescope 日志（非生产环境）',
                'command' => 'telescope:prune',
                'expression' => '20 1 * * *',
                'description' => '每天凌晨 1:20 清理 Telescope 日志（仅非生产环境）',
                'environments' => json_encode(['local', 'development', 'testing']),
                'options_with_value' => json_encode([['name' => 'hours', 'value' => '48']]),
                'on_one_server' => true,
            ],
        ];

        foreach ($tasks as $task) {
            DB::table('schedules')->insert([
                'name' => $task['name'],
                'command' => $task['command'],
                'command_custom' => $task['command_custom'] ?? null,
                'params' => null,
                'options' => $task['options'] ?? null,
                'options_with_value' => $task['options_with_value'] ?? null,
                'expression' => $task['expression'],
                'environments' => $task['environments'] ?? null,
                'description' => $task['description'],
                'even_in_maintenance_mode' => false,
                'without_overlapping' => false,
                'on_one_server' => $task['on_one_server'] ?? false,
                'run_in_background' => false,
                'status' => ScheduleTaskStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('schedules')->whereIn('name', [
            '清理过期密码重置令牌',
            '清理过期模型实例',
            '统计用户数据',
            '同步规则分群成员',
            '清理 Telescope 记录（保留异常）',
            'Horizon 状态快照',
            '关闭超时充值订单',
            '清理 Telescope 日志（非生产环境）',
        ])->delete();
    }
};
