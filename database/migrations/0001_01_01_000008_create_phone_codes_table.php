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
        Schema::create('phone_codes', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('验证码ID');
            $table->string('scene', 20)->nullable()->default('default')->comment('场景');
            $table->string('phone', 20)->index()->comment('手机号');
            $table->string('code', 10)->comment('验证码');
            $table->unsignedTinyInteger('state')->default(0)->comment('验证状态');
            $table->unsignedTinyInteger('verify_count')->default(0)->comment('验证次数');
            $table->ipAddress('ip')->default('')->comment('ip');
            $table->timestamp('send_at')->nullable()->useCurrent()->comment('发送时间');
            $table->timestamp('usage_at')->nullable()->comment('使用时间');
            $table->json('result')->nullable()->comment('发送结果');

            $table->comment('短信验证码表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_codes');
    }
};
