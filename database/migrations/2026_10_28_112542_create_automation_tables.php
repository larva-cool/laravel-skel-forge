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
     * 创建自动化运营相关三张表：
     *  - automation_festivals：节日定义（公历/农历）
     *  - automation_rules：自动化触达规则（生日/节日/注册周年）
     *  - automation_logs：触发日志（唯一索引保证幂等防重）
     */
    public function up(): void
    {
        Schema::create('automation_festivals', function (Blueprint $table) {
            $table->id()->comment('节日 ID');
            $table->string('name', 64)->comment('节日名称');
            $table->string('date_type', 16)->default('solar')->comment('日期类型：solar=公历 lunar=农历');
            $table->unsignedTinyInteger('month')->comment('月份（1-12）');
            $table->unsignedTinyInteger('day')->comment('日（1-31）');
            $table->boolean('status')->default(true)->index()->comment('启用状态：0=禁用 1=启用');
            $table->unsignedInteger('sort')->default(1000)->comment('排序权重，越小越靠前');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date_type', 'month', 'day'], 'idx_festival_date');
            $table->comment('自动化运营-节日定义表');
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id()->comment('规则 ID');
            $table->string('name', 64)->comment('规则名称');
            $table->string('trigger_type', 32)->index()->comment('触发类型：birthday=生日 festival=节日 register_anniversary=注册周年');
            $table->json('trigger_config')->comment('触发配置：advance_days=提前天数 send_time=推送时间 festival_ids=节日范围（仅节日类）');
            $table->foreignId('segment_id')
                ->nullable()
                ->constrained('user_segments')
                ->nullOnDelete()
                ->comment('目标分群 ID，为空表示全量用户');
            $table->string('title', 200)->comment('通知标题模板，支持 {nickname} {festival} {date} 变量');
            $table->string('content', 1000)->comment('通知内容模板，支持 {nickname} {festival} {date} 变量');
            $table->json('channels')->comment('触达渠道：database=站内通知 wechat_mini_program=微信小程序客服消息');
            $table->string('reward_type', 16)->default('none')->comment('奖励类型：none=无 point=积分 coin=金币');
            $table->unsignedInteger('reward_amount')->default(0)->comment('奖励数量');
            $table->boolean('status')->default(true)->index()->comment('启用状态：0=禁用 1=启用');
            $table->timestamp('last_triggered_at')->nullable()->comment('最近一次触发时间');
            $table->unsignedInteger('last_triggered_count')->nullable()->comment('最近一次触达用户数');
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete()
                ->comment('创建人管理员 ID');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('自动化运营-触达规则表');
        });

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id()->comment('日志 ID');
            $table->foreignId('rule_id')
                ->constrained('automation_rules')
                ->cascadeOnDelete()
                ->comment('规则 ID');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('用户 ID');
            $table->date('trigger_date')->comment('事件日期（如生日/节日当天，非实际推送日期）');
            $table->foreignId('festival_id')
                ->nullable()
                ->constrained('automation_festivals')
                ->nullOnDelete()
                ->comment('命中节日 ID（仅节日类规则）');
            $table->json('channels')->nullable()->comment('实际触达渠道快照');
            $table->string('status', 16)->default('success')->index()->comment('状态：success=成功 failed=失败');
            $table->string('error', 500)->nullable()->comment('失败原因');
            $table->timestamps();

            $table->unique(['rule_id', 'user_id', 'trigger_date'], 'uniq_rule_user_date');
            $table->index(['user_id', 'created_at'], 'idx_user_time');
            $table->comment('自动化运营-触发日志表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('automation_festivals');
    }
};
