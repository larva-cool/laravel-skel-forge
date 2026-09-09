<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\WithdrawStatus;
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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->string('owner_type', 64)->comment('持有者类型（多态，如 user）');
            $table->unsignedBigInteger('owner_id')->comment('持有者ID');
            $table->string('order_no', 64)->unique()->comment('提现单号');
            $table->decimal('amount', 16, 2)->comment('申请提现金额');
            $table->decimal('fee', 16, 2)->default(0)->comment('手续费');
            $table->decimal('actual_amount', 16, 2)->comment('实际到账金额（amount - fee）');
            $table->string('account_type', 32)->comment('收款账户类型（WithdrawAccountType 枚举）');
            $table->json('account_info')->comment('收款账户信息');
            $table->string('status', 20)->default(WithdrawStatus::PENDING->value)->comment('提现状态（WithdrawStatus 枚举）');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('审核管理员ID');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->string('reject_reason', 255)->nullable()->comment('驳回原因');
            $table->timestamp('transfer_at')->nullable()->comment('打款完成时间');
            $table->string('fail_reason', 255)->nullable()->comment('打款失败原因');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->string('client_ip', 45)->nullable()->comment('下单IP');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'created_at'], 'idx_withdraw_owner_at');
            $table->index('status', 'idx_withdraw_status');

            $table->comment('提现申请表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};
