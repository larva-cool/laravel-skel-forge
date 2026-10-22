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
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->comment('所属文档 ID');
            $table->text('content')->comment('分块文本内容');
            $table->json('embedding')->comment('向量（JSON 数组，维度由配置的 Embedding 模型决定）');
            $table->unsignedInteger('tokens')->default(0)->comment('该分块向量化消耗的 Tokens');
            $table->unsignedSmallInteger('sort')->default(0)->comment('分块在文档内的顺序');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('knowledge_documents')->cascadeOnDelete();
            $table->index('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
