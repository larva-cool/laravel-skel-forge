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
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('管理员ID');
            $table->string('username')->unique()->nullable()->comment('用户名');
            $table->string('email')->unique()->nullable()->comment('邮箱');
            $table->string('phone', 11)->unique()->nullable()->comment('手机号');
            $table->string('name')->nullable()->comment('昵称');
            $table->string('avatar', 500)->nullable()->comment('头像URL');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1、active，0、frozen');
            $table->string('password')->nullable()->comment('密码');
            $table->rememberToken()->comment('记住我token');
            $table->unsignedBigInteger('login_count')->nullable()->default(0)->comment('登录次数');
            $table->ipAddress('last_login_ip')->nullable()->comment('最后登录IP地址');
            $table->timestamp('last_active_at')->nullable()->comment('最后活动时间');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes()->comment('删除时间');

            $table->comment('管理员表');
        });
        Schema::create('admin_menus', function (Blueprint $table) {
            $table->id()->comment('菜单ID');
            $table->unsignedBigInteger('parent_id')->nullable()->index()->comment('父级菜单ID，null 表示顶级菜单');
            $table->string('path', 255)->nullable()->comment('路由路径');
            $table->string('name', 100)->nullable()->comment('路由名称（唯一标识）');
            $table->string('component', 255)->nullable()->comment('前端组件路径');
            $table->string('redirect', 255)->nullable()->comment('重定向路径');
            $table->string('title', 100)->comment('菜单标题');
            $table->string('icon', 100)->nullable()->comment('菜单图标');
            $table->string('link', 500)->nullable()->comment('外部链接地址');
            $table->unsignedTinyInteger('type')->default(1)->comment('菜单类型：0目录、1菜单、2按钮、3内嵌、4外链');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，越小越靠前');
            $table->boolean('is_enable')->default(true)->comment('是否启用');
            $table->boolean('is_hide')->default(false)->comment('是否在菜单中隐藏');
            $table->boolean('is_hide_tab')->default(false)->comment('是否在标签页中隐藏');
            $table->boolean('is_iframe')->default(false)->comment('是否以 iframe 方式内嵌');
            $table->boolean('keep_alive')->default(false)->comment('是否开启页面缓存');
            $table->boolean('is_full_page')->default(false)->comment('是否全屏页面');
            $table->boolean('fixed_tab')->default(false)->comment('是否固定标签页');
            $table->boolean('show_badge')->default(false)->comment('是否显示红点徽章');
            $table->string('show_text_badge', 50)->nullable()->comment('文本徽章内容');
            $table->string('active_path', 255)->nullable()->comment('激活菜单高亮路径');
            $table->string('permission', 100)->nullable()->comment('权限标识（authMark）');
            $table->timestamps();
            $table->softDeletes()->comment('删除时间');

            $table->index(['parent_id', 'sort']);
            $table->comment('后台菜单表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_menus');
        Schema::dropIfExists('admin_users');
    }
};
