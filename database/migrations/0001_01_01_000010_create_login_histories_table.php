<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id()->from(10000000);
            $table->string('user_type')->comment('用户类型');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->ipAddress('ip')->comment('登录IP');
            $table->unsignedInteger('port')->nullable()->comment('登录端口');
            $table->string('platform', 50)->nullable()->default('Unknown')->comment('系统平台');
            $table->string('device')->nullable()->comment('登录设备');
            $table->string('browser')->nullable()->default('Unknown')->comment('浏览器平台');
            $table->string('user_agent', 1200)->nullable()->comment('浏览器UA');
            $table->string('address', 1000)->nullable()->comment('地址');
            $table->timestamp('login_at')->nullable()->useCurrent()->comment('登录时间');
            $table->index(['user_type', 'user_id', 'login_at'], 'idx_user_type_id_login_at');
            $table->comment('登录历史表');
        });

        if (DB::connection()->getConfig('driver') == 'mysql') {
            $tablePrefix = DB::connection()->getTablePrefix();
            DB::statement('ALTER TABLE `'.$tablePrefix.'login_histories` MODIFY COLUMN `ip` VARBINARY(16)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
