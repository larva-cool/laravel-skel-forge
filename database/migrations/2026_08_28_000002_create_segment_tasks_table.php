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
     *
     * 异步分群任务表（用于跟踪 > 1000 ID 的批量打标/解标进度）。
     */
    public function up(): void
    {
        Schema::create('segment_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->comment('提交任务的管理员 ID');
            $table->string('type', 32)->index()->comment('任务类型：attach / detach');
            $table->json('payload')->comment('任务载荷：segment_ids / user_ids');
            $table->unsignedInteger('total_count')->default(0)->comment('计划处理总数');
            $table->unsignedInteger('processed_count')->default(0)->comment('已处理数');
            $table->unsignedInteger('added_count')->default(0)->comment('实际新增/解绑数');
            $table->string('status', 16)->default('pending')->index()->comment('状态：pending / running / completed / failed');
            $table->string('error_message', 1000)->nullable()->comment('失败原因');
            $table->timestamp('started_at')->nullable()->comment('开始执行时间');
            $table->timestamp('finished_at')->nullable()->comment('完成时间');
            $table->timestamps();

            $table->index('admin_id');
            $table->index(['status', 'created_at']);
            $table->comment('用户分群异步任务表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segment_tasks');
    }
};
