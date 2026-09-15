<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensitive_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 64)->unique()->comment('敏感词（大小写不敏感，由 DFA 服务控制）');
            $table->string('category', 20)->default(SensitiveCategory::OTHER->value)->comment('分类');
            $table->string('level', 20)->default(SensitiveLevel::REVIEW->value)->comment('命中级别');
            $table->string('replacement', 16)->default('*')->comment('替换字符（REPLACE 级别使用）');
            $table->unsignedInteger('hit_count')->default(0)->comment('命中次数（统计用）');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'level']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_words');
    }
};
