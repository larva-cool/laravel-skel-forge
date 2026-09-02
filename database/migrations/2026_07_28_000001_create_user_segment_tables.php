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
     * 创建用户分群（segment）相关两张表：
     *  - user_segments：分群定义（含手动 / 规则两种类型）
     *  - user_segment_memberships：分群成员（用户 ↔ 分群 多对多）
     */
    public function up(): void
    {
        Schema::create('user_segments', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('分群 ID');
            $table->string('code', 64)->nullable()->unique()->comment('分群编码（规则分群必填且唯一，手动可空）');
            $table->string('name', 64)->comment('分群名称');
            $table->string('label_type', 16)->default('default')->comment('标签样式：default/primary/success/warning/danger/info');
            $table->unsignedTinyInteger('type')->index()->comment('分群类型：1=手动 2=规则');
            $table->string('group', 32)->nullable()->index()->comment('分组（用户画像/运营分群/风控 等）');
            $table->string('description', 500)->nullable()->comment('分群说明');
            $table->unsignedInteger('sort')->default(1000)->comment('排序权重，越小越靠前');
            $table->boolean('status')->default(true)->index()->comment('启用状态：0=禁用 1=启用');
            $table->json('rule')->nullable()->comment('规则定义（仅 type=2 有效，结构详见 SegmentRuleEvaluator）');
            $table->timestamp('last_synced_at')->nullable()->comment('最近一次规则刷新时间');
            $table->unsignedInteger('last_synced_user_count')->nullable()->comment('最近一次规则匹配用户数');
            $table->timestamps();

            $table->comment('用户分群定义表');
        });

        Schema::create('user_segment_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')
                ->constrained('user_segments')
                ->cascadeOnDelete()
                ->comment('分群 ID');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('用户 ID');
            $table->unsignedTinyInteger('source')->default(1)->comment('打标来源：1=手动 2=规则');
            $table->timestamp('created_at')->nullable();

            $table->unique(['segment_id', 'user_id'], 'uniq_segment_user');
            $table->index(['user_id', 'segment_id'], 'idx_user_segment');
            $table->index(['segment_id', 'source', 'created_at'], 'idx_segment_source_time');

            $table->comment('用户分群成员表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_segment_memberships');
        Schema::dropIfExists('user_segments');
    }
};
