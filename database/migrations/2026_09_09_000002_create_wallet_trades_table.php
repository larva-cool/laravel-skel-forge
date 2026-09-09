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
        Schema::create('wallet_trades', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->string('owner_type', 64)->comment('持有者类型（多态，如 user）');
            $table->unsignedBigInteger('owner_id')->comment('持有者ID');
            $table->decimal('amount', 16, 2)->comment('本次变动金额（正增负减）');
            $table->decimal('before_balance', 16, 2)->comment('变动前可用余额');
            $table->decimal('after_balance', 16, 2)->comment('变动后可用余额');
            $table->decimal('before_frozen', 16, 2)->comment('变动前冻结余额');
            $table->decimal('after_frozen', 16, 2)->comment('变动后冻结余额');
            $table->string('type', 32)->comment('交易类型（WalletTradeType 枚举）');
            $table->string('source_type', 64)->nullable()->comment('业务来源类型（多态）');
            $table->unsignedBigInteger('source_id')->nullable()->comment('业务来源ID');
            $table->string('status', 20)->default('success')->comment('流水状态');
            $table->string('description', 255)->nullable()->comment('交易描述');
            $table->timestamp('created_at')->comment('交易时间');
            $table->index(['owner_type', 'owner_id', 'created_at'], 'idx_wallet_trade_owner_at');
            $table->index(['source_type', 'source_id'], 'idx_wallet_trade_source');
            $table->index('type', 'idx_wallet_trade_type');

            $table->comment('钱包流水表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_trades');
    }
};
