<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\WithdrawAccountStatus;
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
        Schema::create('withdraw_accounts', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->string('owner_type', 64)->comment('持有者类型（多态，如 user）');
            $table->unsignedBigInteger('owner_id')->comment('持有者ID');
            $table->string('account_type', 32)->comment('账户类型（WithdrawAccountType 枚举）');
            $table->string('real_name', 32)->comment('真实姓名');
            $table->string('account_no', 64)->comment('收款账号（微信OpenID/支付宝账号/银行卡号）');
            $table->string('bank_name', 64)->nullable()->comment('开户银行（仅银行卡）');
            $table->string('bank_branch', 128)->nullable()->comment('支行名称（仅银行卡）');
            $table->boolean('is_default')->default(false)->comment('是否默认账户');
            $table->boolean('is_verified')->default(false)->comment('是否已验证');
            $table->timestamp('last_used_at')->nullable()->comment('最后使用时间');
            $table->string('status', 20)->default(WithdrawAccountStatus::ACTIVE->value)->comment('状态（WithdrawAccountStatus 枚举）');
            $table->timestamps();
            $table->softDeletes();

            // 同一持有者下同类型同账号不可重复绑定
            $table->unique(['owner_type', 'owner_id', 'account_type', 'account_no'], 'uk_withdraw_account');
            // 查询用户所有账户
            $table->index(['owner_type', 'owner_id', 'account_type', 'is_default'], 'idx_withdraw_account_default');
            $table->index(['owner_type', 'owner_id', 'status'], 'idx_withdraw_account_status');

            $table->comment('提现收款账户表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraw_accounts');
    }
};
