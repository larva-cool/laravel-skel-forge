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
        Schema::create('agreements', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('协议ID');
            $table->string('title', 200)->comment('协议标题');
            $table->string('category', 50)->comment('协议类别（字典值）');
            $table->longText('content')->comment('协议内容（富文本 HTML）');
            $table->string('version', 20)->comment('版本号');
            $table->string('status', 20)->default('draft')->comment('状态：draft草稿 published已发布 archived已归档');
            $table->timestamp('effective_at')->nullable()->comment('生效时间');
            $table->unsignedInteger('sort')->default(1000)->comment('排序权重，越小越靠前');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('创建/编辑管理员ID');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status']);
            $table->index('admin_id');

            $table->comment('用户协议表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
