<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\Task\TaskClaimMode;
use App\Enums\Task\TaskGroupType;
use App\Enums\Task\TaskProgressStatus;
use App\Enums\Task\TaskResetType;
use App\Enums\Task\TaskRewardType;
use App\Enums\Task\TaskType;
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
        // 任务分组表
        Schema::create('task_groups', function (Blueprint $table) {
            $table->id()->comment('分组ID');
            $table->string('name', 50)->comment('分组名称');
            $table->string('code', 50)->unique()->comment('分组编码（唯一标识）');
            $table->unsignedTinyInteger('type')->default(TaskGroupType::DAILY->value)->comment('分组类型：0新手、1每日、2每周、3签到、9自定义');
            $table->unsignedTinyInteger('reset_type')->default(TaskResetType::NONE->value)->comment('重置类型：0不重置、1每日、2每周、3每月');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序值（越小越靠前）');
            $table->string('icon', 500)->nullable()->comment('图标URL');
            $table->text('description')->nullable()->comment('分组描述');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->comment('任务分组表');
        });

        // 任务定义表
        Schema::create('tasks', function (Blueprint $table) {
            $table->id()->comment('任务ID');
            $table->foreignId('group_id')->constrained('task_groups')->cascadeOnDelete()->comment('所属分组ID');
            $table->string('name', 100)->comment('任务名称');
            $table->string('code', 100)->unique()->comment('任务编码（唯一标识）');
            $table->string('type', 30)->default(TaskType::CUSTOM_ACTION->value)->comment('任务类型');
            $table->text('description')->nullable()->comment('任务描述');
            $table->json('condition')->nullable()->comment('任务条件JSON（{serial_days, target_value, completeness_threshold}）');
            $table->string('reward_type', 20)->default(TaskRewardType::COIN->value)->comment('奖励类型：coin/point/vip_days');
            $table->unsignedInteger('reward_amount')->default(0)->comment('奖励数量');
            $table->unsignedTinyInteger('claim_mode')->default(TaskClaimMode::AUTO->value)->comment('领取方式：0自动、1手动');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序值');
            $table->timestamp('start_at')->nullable()->comment('生效开始时间');
            $table->timestamp('end_at')->nullable()->comment('生效结束时间');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->comment('任务定义表');
        });

        // 用户任务进度表
        Schema::create('task_progress', function (Blueprint $table) {
            $table->id()->comment('进度ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete()->comment('任务ID');
            $table->string('period_key', 32)->default('all')->comment('周期标识（如2026-09-09、all、sign_in_day_7）');
            $table->unsignedInteger('current_value')->default(0)->comment('当前进度值');
            $table->unsignedTinyInteger('status')->default(TaskProgressStatus::IN_PROGRESS->value)->comment('状态：0进行中、1已完成、2已领取、3已过期');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamp('rewarded_at')->nullable()->comment('奖励发放时间');
            $table->timestamps();

            $table->unique(['user_id', 'task_id', 'period_key'], 'task_progress_unique');
            $table->index(['user_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->comment('用户任务进度表');
        });

        // 任务完成/奖励发放日志表
        Schema::create('task_logs', function (Blueprint $table) {
            $table->id()->comment('日志ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete()->comment('任务ID');
            $table->string('period_key', 32)->default('all')->comment('周期标识');
            $table->string('reward_type', 20)->comment('奖励类型');
            $table->unsignedInteger('reward_amount')->comment('奖励数量');
            $table->string('source_type', 50)->nullable()->comment('触发来源类型（事件类名）');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamp('created_at')->useCurrent()->comment('记录时间');

            $table->index(['user_id', 'task_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->comment('任务完成/奖励发放日志表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_logs');
        Schema::dropIfExists('task_progress');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_groups');
    }
};
