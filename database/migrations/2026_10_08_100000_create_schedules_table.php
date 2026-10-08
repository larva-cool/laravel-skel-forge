<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->string('name', 100)->comment('任务名称');
            $table->string('command', 255)->comment('Artisan 命令名（如 stats:user）或 custom（自定义 shell）');
            $table->string('command_custom', 500)->nullable()->comment('自定义 Shell 命令（command=custom 时使用）');
            $table->json('params')->nullable()->comment('命令参数（JSON 数组）');
            $table->json('options')->nullable()->comment('命令选项（JSON 数组，无值选项）');
            $table->json('options_with_value')->nullable()->comment('带值命令选项（JSON 键值对）');
            $table->string('expression', 64)->default('* * * * *')->comment('Cron 表达式');
            $table->json('environments')->nullable()->comment('执行环境限定（JSON 数组，如 ["production"]）');
            $table->string('description', 255)->nullable()->comment('任务描述');
            $table->boolean('even_in_maintenance_mode')->default(false)->comment('维护模式下也执行');
            $table->boolean('without_overlapping')->default(false)->comment('禁止重叠执行');
            $table->boolean('on_one_server')->default(false)->comment('仅在单台服务器上执行');
            $table->boolean('run_in_background')->default(false)->comment('后台运行');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0 禁用 1 启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_schedules_status');
            $table->index('command', 'idx_schedules_command');
            $table->comment('定时任务管理表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
