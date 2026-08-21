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
        Schema::create('point_records', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->integer('points')->comment('本次交易的积分数');
            $table->string('description')->comment('描述');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->timestamps();

            $table->index(['user_id', 'expired_at'], 'idx_point_records');

            $table->comment('积分记录表');
        });
        Schema::create('point_trades', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->integer('points')->comment('本次交易的积分数');
            $table->string('description')->comment('描述');
            $table->string('source_type')->comment('源类型');
            $table->unsignedBigInteger('source_id')->comment('源ID');
            $table->string('type')->comment('交易类型');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->index(['user_id', 'source_type', 'source_id', 'expired_at'], 'idx_point_trades');

            $table->comment('积分交易流水表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_trades');
        Schema::dropIfExists('point_records');
    }
};
