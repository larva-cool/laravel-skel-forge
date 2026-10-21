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
        Schema::create('chat_models', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('volc')->comment('AI 服务商');
            $table->string('model_id', 100)->comment('模型 ID（服务商侧标识）');
            $table->string('name', 100)->comment('展示名称');
            $table->boolean('is_default')->default(false)->comment('是否默认模型');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->unsignedSmallInteger('sort')->default(0)->comment('排序（越小越靠前）');
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_models');
    }
};
