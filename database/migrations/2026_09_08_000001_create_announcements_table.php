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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('公告ID');
            $table->string('title', 200)->comment('标题');
            $table->string('summary', 500)->nullable()->comment('摘要');
            $table->longText('content')->comment('正文（富文本 HTML）');
            $table->string('cover_image', 500)->nullable()->comment('封面图 URL');
            $table->string('status', 20)->default('draft')->index()->comment('状态：draft 草稿 / published 已发布 / archived 已归档');
            $table->integer('sort')->default(1000)->comment('排序权重');
            $table->unsignedBigInteger('admin_id')->index()->comment('创建人管理员ID');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('公告表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
