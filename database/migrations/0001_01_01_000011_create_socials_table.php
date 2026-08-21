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
        Schema::create('socials', function (Blueprint $table) {
            $table->id();
            $table->morphs('user');
            $table->string('provider')->comment('服务渠道');
            $table->string('openid')->comment('开放平台ID');
            $table->string('unionid')->nullable()->unique()->comment('联合ID');
            $table->string('access_token')->nullable()->comment('访问令牌');
            $table->string('refresh_token')->nullable()->comment('刷新令牌');
            $table->timestamp('expiry_at')->nullable()->comment('过期时间');
            $table->timestamps();

            $table->index(['provider', 'openid', 'unionid']);
            $table->comment('用户社交账号表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('socials');
    }
};
