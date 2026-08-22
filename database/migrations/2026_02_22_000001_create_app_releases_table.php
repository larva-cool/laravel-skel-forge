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

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('版本ID');
            $table->unsignedBigInteger('app_release_id')->comment('所属应用ID');
            $table->string('platform', 20)->comment('平台：android/ios/harmony');
            $table->string('version', 50)->comment('语义化版本号，如 1.2.3');
            $table->unsignedInteger('build')->comment('构建号，用于版本比较');
            $table->string('download_url', 500)->comment('下载地址');
            $table->text('release_notes')->nullable()->comment('更新说明');
            $table->string('min_supported_version', 50)->nullable()->comment('最低支持版本');
            $table->unsignedInteger('min_supported_build')->default(0)->comment('最低支持构建号，低于此值强制更新');
            $table->boolean('force_update')->default(false)->comment('是否强制更新');
            $table->unsignedBigInteger('file_size')->nullable()->comment('安装包大小（字节）');
            $table->string('file_hash', 128)->nullable()->comment('文件哈希校验值');
            $table->string('status', 20)->default('draft')->comment('发布状态：draft/published/withdrawn/obsolete');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('app_release_id')->references('id')->on('app_releases')->cascadeOnDelete();
            $table->index(['app_release_id', 'platform', 'status'], 'idx_app_platform_status');
            $table->index(['app_release_id', 'platform', 'build'], 'idx_app_platform_build');
            $table->comment('App版本表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
        Schema::dropIfExists('app_releases');
    }
};
