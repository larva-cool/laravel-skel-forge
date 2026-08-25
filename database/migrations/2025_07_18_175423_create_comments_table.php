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
        Schema::create('comments', function (Blueprint $table) {
            $table->id()->comment('评论ID');
            $table->unsignedBigInteger('user_id')->index()->comment('评论用户');
            $table->morphs('source');
            $table->boolean('is_top')->default(false)->comment('评论置顶');
            $table->string('status')->nullable()->comment('评论状态');
            $table->unsignedInteger('like_count')->nullable()->default(0)->comment('点赞次数');
            $table->unsignedInteger('comment_count')->nullable()->default(0)->comment('评论回复次数');
            $table->string('content', 1000)->comment('评论内容');
            $table->json('mentioned_users')->nullable()->comment('艾特 / 提及');
            $table->ipAddress('ip_address')->nullable()->comment('评论者IP');
            $table->timestamp('created_at')->nullable()->comment('评论时间');

            $table->index(['user_id', 'created_at'], 'idx_comment_user_at');
            $table->index(['source_type', 'source_id', 'is_top', 'status', 'created_at'], 'idx_comment_source_at');
            $table->comment('评论表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
