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
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->comment('文档标题');
            $table->longText('content')->comment('文档原始内容');
            $table->string('status', 20)->default('pending')->comment('索引状态：pending=待处理，processing=处理中，ready=已完成，failed=失败');
            $table->boolean('is_enabled')->default(true)->comment('是否启用（停用的文档不参与检索）');
            $table->unsignedInteger('chunks_count')->default(0)->comment('分块数量');
            $table->unsignedInteger('tokens')->default(0)->comment('向量化消耗的 Tokens');
            $table->string('error', 500)->nullable()->comment('最近一次索引失败原因');
            $table->unsignedBigInteger('created_by')->nullable()->index()->comment('创建管理员 ID');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
