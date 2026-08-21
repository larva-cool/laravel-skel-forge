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
        Schema::create('schedule_logs', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('日志ID');
            $table->string('name')->comment('任务名称');
            $table->string('type', 16)->default('command')->comment('任务类型：command/callback/exec');
            $table->string('expression', 64)->default('')->comment('Cron 表达式');
            $table->unsignedTinyInteger('status')->default(0)->comment('执行状态：0执行中 1成功 2失败 3跳过');
            $table->smallInteger('exit_code')->nullable()->comment('退出码');
            $table->decimal('runtime', 10, 3)->nullable()->comment('执行耗时（秒）');
            $table->text('exception')->nullable()->comment('异常信息');
            $table->string('hostname', 64)->nullable()->comment('执行主机名');
            $table->timestamp('started_at')->nullable()->useCurrent()->comment('开始时间');
            $table->timestamp('finished_at')->nullable()->comment('结束时间');

            $table->index(['name', 'started_at'], 'idx_name_started_at');
            $table->index('status');
            $table->index('started_at');
            $table->comment('调度任务执行日志表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_logs');
    }
};
