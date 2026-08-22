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
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('应用ID');
            $table->string('name', 100)->comment('应用名称');
            $table->string('slug', 100)->unique()->comment('应用唯一标识');
            $table->string('description', 500)->nullable()->comment('应用描述');
            $table->string('icon', 500)->nullable()->comment('应用图标URL');
            $table->unsignedSmallInteger('sort')->default(0)->comment('排序权重');
            $table->boolean('status')->default(true)->comment('状态：true启用 false禁用');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('应用发布表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
