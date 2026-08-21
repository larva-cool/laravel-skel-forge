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
        Schema::create('coin_trades', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->integer('coins')->comment('本次交易的金币数');
            $table->string('description')->comment('描述');
            $table->string('source_type')->comment('源类型');
            $table->unsignedBigInteger('source_id')->comment('源ID');
            $table->string('type')->comment('交易类型');
            $table->timestamp('created_at')->comment('交易时间');
            $table->index(['user_id', 'source_type', 'source_id', 'created_at'], 'idx_coin_trade_user_at');

            $table->comment('金币交易流水表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_trades');
    }
};
