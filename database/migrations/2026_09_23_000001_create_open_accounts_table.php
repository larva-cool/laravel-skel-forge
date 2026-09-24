<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\IdentityStatus;
use App\Enums\UserStatus;
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
        Schema::create('open_accounts', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('账户ID');
            $table->string('username', 50)->unique()->nullable()->comment('用户名');
            $table->string('email')->unique()->nullable()->comment('邮箱');
            $table->string('phone', 30)->unique()->nullable()->comment('手机号（支持国际格式，如+8613800138000）');
            $table->string('name', 50)->nullable()->comment('合作方名称');
            $table->string('contact_name')->nullable()->comment('联系人');
            $table->string('contact_phone', 30)->nullable()->comment('联系电话');
            $table->string('contact_email')->nullable()->comment('联系邮箱');
            $table->unsignedInteger('rate_limit')->default(60)->comment('每分钟请求上限（0 使用全局默认）');
            $table->unsignedInteger('available_points')->nullable()->default(0)->comment('可用积分');
            $table->unsignedInteger('available_coins')->nullable()->default(0)->comment('可用金币');
            $table->decimal('balance', 16, 2)->default(0)->comment('可用余额（真实货币，元）');
            $table->decimal('frozen_balance', 16, 2)->default(0)->comment('冻结余额（真实货币，元）');
            $table->unsignedTinyInteger('status')->default(UserStatus::FROZEN->value)->comment('状态：0、frozen，1、active');
            $table->string('identity_type', 16)->nullable()->comment('实名认证类型：personal / enterprise');
            $table->string('identity_status', 16)->default(IdentityStatus::UNSUBMITTED->value)->index()->comment('实名认证状态：unsubmitted 未提交 / pending 待审核 / approved 已通过 / rejected 已驳回');
            $table->string('password')->nullable()->comment('密码');
            $table->rememberToken()->comment('记住我token');
            $table->unsignedBigInteger('login_count')->nullable()->default(0)->comment('登录次数');
            $table->ipAddress('last_login_ip')->nullable()->comment('最后登录IP地址');
            $table->timestamp('last_active_at')->nullable()->comment('最后活动时间');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamp('last_request_at')->nullable()->comment('最近调用时间');
            $table->ipAddress('last_request_ip')->nullable()->comment('最近调用IP');
            $table->timestamps();
            $table->softDeletes()->comment('删除时间');

            $table->index('status');
            $table->comment('开放平台账户表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_accounts');
    }
};
