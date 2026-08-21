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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('附件ID');
            $table->nullableMorphs('uploader');
            $table->string('disk', 32)->comment('存储磁盘');
            $table->string('path')->comment('存储路径');
            $table->string('name')->comment('显示名称');
            $table->string('original_name')->comment('原始文件名');
            $table->string('extension', 32)->default('')->comment('扩展名');
            $table->string('mime_type', 128)->default('')->comment('MIME类型');
            $table->string('type', 16)->default('other')->comment('附件类型');
            $table->unsignedBigInteger('size')->default(0)->comment('文件字节数');
            $table->char('hash', 32)->nullable()->comment('文件MD5');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path']);
            $table->index('type');
            $table->index('hash');
            $table->comment('附件表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
