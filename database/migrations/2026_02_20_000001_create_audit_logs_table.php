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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('日志ID');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->string('operator_name', 64)->nullable()->comment('操作人名称快照');
            $table->string('route', 128)->nullable()->comment('路由名称');
            $table->string('method', 8)->comment('请求方法');
            $table->string('uri', 255)->comment('请求路径');
            $table->string('title', 128)->nullable()->comment('操作描述');
            $table->json('payload')->nullable()->comment('请求参数（已脱敏）');
            $table->unsignedSmallInteger('status_code')->default(0)->comment('响应状态码');
            $table->text('error')->nullable()->comment('失败时的响应消息');
            $table->decimal('runtime', 10, 3)->nullable()->comment('执行耗时（秒）');
            $table->string('ip', 64)->nullable()->comment('请求IP');
            $table->string('user_agent', 512)->nullable()->comment('用户代理');
            $table->timestamp('created_at')->nullable()->useCurrent()->comment('操作时间');

            $table->index(['operator_id', 'created_at'], 'idx_operator_created_at');
            $table->index(['route', 'created_at'], 'idx_route_created_at');
            $table->index('created_at');
            $table->comment('操作审计日志表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
