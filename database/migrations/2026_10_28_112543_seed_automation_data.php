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
     * 初始化自动化运营数据：
     *  1. 预置常用节日（公历 + 农历）
     *  2. 注册 automation:run 到 schedules 动态调度表（每小时第 10 分钟执行）
     */
    public function up(): void
    {
        $now = now();

        // 1. 预置节日（存在同名节日则跳过，保证重复执行安全）
        $festivals = [
            // 公历节日
            ['name' => '元旦', 'date_type' => 'solar', 'month' => 1, 'day' => 1],
            ['name' => '情人节', 'date_type' => 'solar', 'month' => 2, 'day' => 14],
            ['name' => '妇女节', 'date_type' => 'solar', 'month' => 3, 'day' => 8],
            ['name' => '劳动节', 'date_type' => 'solar', 'month' => 5, 'day' => 1],
            ['name' => '儿童节', 'date_type' => 'solar', 'month' => 6, 'day' => 1],
            ['name' => '国庆节', 'date_type' => 'solar', 'month' => 10, 'day' => 1],
            ['name' => '平安夜', 'date_type' => 'solar', 'month' => 12, 'day' => 24],
            ['name' => '圣诞节', 'date_type' => 'solar', 'month' => 12, 'day' => 25],
            // 农历节日
            ['name' => '春节', 'date_type' => 'lunar', 'month' => 1, 'day' => 1],
            ['name' => '元宵节', 'date_type' => 'lunar', 'month' => 1, 'day' => 15],
            ['name' => '端午节', 'date_type' => 'lunar', 'month' => 5, 'day' => 5],
            ['name' => '七夕节', 'date_type' => 'lunar', 'month' => 7, 'day' => 7],
            ['name' => '中秋节', 'date_type' => 'lunar', 'month' => 8, 'day' => 15],
            ['name' => '重阳节', 'date_type' => 'lunar', 'month' => 9, 'day' => 9],
        ];

        foreach ($festivals as $i => $festival) {
            $exists = DB::table('automation_festivals')
                ->where('name', $festival['name'])
                ->where('date_type', $festival['date_type'])
                ->exists();

            if (! $exists) {
                DB::table('automation_festivals')->insert([
                    ...$festival,
                    'status' => true,
                    'sort' => ($i + 1) * 10,
                    'remark' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2. 注册动态调度任务
        $exists = DB::table('schedules')->where('command', 'automation:run')->exists();

        if (! $exists) {
            DB::table('schedules')->insert([
                'name' => '执行自动化运营规则',
                'command' => 'automation:run',
                'command_custom' => null,
                'params' => null,
                'options' => null,
                'options_with_value' => null,
                'expression' => '10 * * * *',
                'environments' => null,
                'description' => '每小时第 10 分钟扫描启用的自动化运营规则（生日/节日/注册周年），对到达推送时间的规则执行人群匹配与触达',
                'even_in_maintenance_mode' => false,
                'without_overlapping' => true,
                'on_one_server' => true,
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
        DB::table('schedules')->where('command', 'automation:run')->delete();

        DB::table('automation_festivals')->whereIn('name', [
            '元旦', '情人节', '妇女节', '劳动节', '儿童节', '国庆节', '平安夜', '圣诞节',
            '春节', '元宵节', '端午节', '七夕节', '中秋节', '重阳节',
        ])->delete();
    }
};
