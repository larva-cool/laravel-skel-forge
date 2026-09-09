<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\RechargeStatus;
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
        Schema::create('recharges', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->string('owner_type', 64)->comment('持有者类型（多态，如 user）');
            $table->unsignedBigInteger('owner_id')->comment('持有者ID');
            $table->string('order_no', 64)->unique()->comment('订单号');
            $table->decimal('amount', 16, 2)->comment('充值金额');
            $table->decimal('bonus_amount', 16, 2)->default(0)->comment('赠送金额');
            $table->string('pay_channel', 32)->comment('支付渠道（PayChannel 枚举）');
            $table->string('pay_status', 20)->default(RechargeStatus::PENDING->value)->comment('支付状态（RechargeStatus 枚举）');
            // 渠道流水号：唯一索引作为重复入账的数据库层兜底，手动确认场景为空
            $table->string('transaction_id', 128)->nullable()->unique()->comment('渠道流水号');
            $table->unsignedBigInteger('confirmed_by')->nullable()->comment('手动确认到账的管理员ID');
            $table->timestamp('paid_at')->nullable()->comment('支付完成时间');
            $table->timestamp('expired_at')->nullable()->comment('订单过期时间');
            $table->string('client_ip', 45)->nullable()->comment('下单IP');
            $table->string('remark', 255)->nullable()->comment('备注');
            // 预留：支付渠道接入后存放原始回调报文，供对账排查
            $table->json('callback_payload')->nullable()->comment('渠道原始回调报文');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'created_at'], 'idx_recharge_owner_at');
            $table->index('pay_status', 'idx_recharge_status');
            $table->index('expired_at', 'idx_recharge_expired_at');

            $table->comment('充值订单表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharges');
    }
};
