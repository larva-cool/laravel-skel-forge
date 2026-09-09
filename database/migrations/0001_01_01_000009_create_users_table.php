<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('用户ID');
            $table->string('username', 50)->unique()->nullable()->comment('用户名');
            $table->string('email')->unique()->nullable()->comment('邮箱');
            $table->string('phone', 30)->unique()->nullable()->comment('手机号（支持国际格式，如+8613800138000）');
            $table->string('name', 50)->nullable()->comment('昵称');
            $table->string('avatar', 1000)->nullable()->comment('头像');
            $table->unsignedTinyInteger('status')->default(UserStatus::ACTIVE->value)->comment('状态：0、frozen,1、active，2、not_active');
            $table->string('identity_type', 16)->nullable()->comment('实名认证类型：personal / enterprise');
            $table->boolean('identity_verified')->index()->default(false)->comment('是否已通过实名认证');
            $table->unsignedInteger('available_points')->nullable()->default(0)->comment('可用积分');
            $table->unsignedInteger('available_coins')->nullable()->default(0)->comment('可用金币');
            $table->string('password')->nullable()->comment('密码');
            $table->rememberToken()->comment('记住我token');
            $table->unsignedBigInteger('login_count')->nullable()->default(0)->comment('登录次数');
            $table->ipAddress('last_login_ip')->nullable()->comment('最后登录IP地址');
            $table->dateTime('vip_expires_at')->nullable()->comment('VIP过期时间');
            $table->timestamp('last_active_at')->nullable()->comment('最后活动时间');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes()->comment('删除时间');

            $table->comment('用户表');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary()->comment('用户ID');
            $table->unsignedTinyInteger('gender')->default(0)->comment('性别：0、保密,1、男，2、女');
            $table->date('birthday')->nullable()->comment('生日');
            $table->unsignedInteger('province_id')->nullable()->comment('省ID');
            $table->unsignedInteger('city_id')->nullable()->comment('市ID');
            $table->unsignedInteger('district_id')->nullable()->comment('区ID');
            $table->string('website', 255)->nullable()->comment('个人网站');
            $table->text('intro')->nullable()->comment('个人介绍');
            $table->text('bio')->nullable()->comment('个性签名');

            $table->comment('用户个人资料表');
        });

        Schema::create('user_extras', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary()->comment('用户ID');
            $table->unsignedTinyInteger('username_change_count')->default(0)->nullable()->comment('用户名修改次数');
            $table->timestamp('email_verified_at')->nullable()->comment('邮件验证时间');

            $table->comment('用户扩展信息表');
        });

        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique()->comment('统计日期');
            $table->unsignedBigInteger('total_user_count')->default(0)->comment('用户总数');
            $table->unsignedBigInteger('new_user_count')->default(0)->comment('注册用户数');
            $table->unsignedBigInteger('active_user_count')->default(0)->comment('活跃用户总数');
            $table->unsignedBigInteger('total_point_count')->default(0)->comment('累计可用积分总量');
            $table->unsignedBigInteger('incr_point_count')->default(0)->comment('当日发放积分总量');
            $table->unsignedBigInteger('decr_point_count')->default(0)->comment('当日消耗积分总量');
            $table->unsignedBigInteger('total_coin_count')->default(0)->comment('累计可用金币总量');
            $table->unsignedBigInteger('incr_coin_count')->default(0)->comment('当日发放金币总量');
            $table->unsignedBigInteger('decr_coin_count')->default(0)->comment('当日消耗金币总量');
            $table->timestamp('created_at')->nullable()->comment('统计时间');

            $table->comment('用户统计表');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('邮箱');
            $table->string('token')->comment('Token');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->comment('密码重置表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('user_extras');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};
