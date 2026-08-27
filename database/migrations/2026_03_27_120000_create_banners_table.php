<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Models\System\Dict;
use App\Models\System\DictItem;
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
        Schema::create('banners', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('Banner ID');
            $table->string('position', 64)->index()->comment('广告位标识（对应字典 banner_positions）');
            $table->string('title', 200)->default('')->comment('标题');
            $table->string('image', 500)->comment('图片 URL');
            $table->string('link_type', 32)->default('none')->comment('链接类型：none无链接 url外部链接 page内部页面');
            $table->string('link_value', 500)->nullable()->comment('链接值（对应 link_type）');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，越小越靠前');
            $table->boolean('status')->default(true)->index()->comment('启用状态：0禁用 1启用');
            $table->timestamp('start_at')->nullable()->comment('生效开始时间');
            $table->timestamp('end_at')->nullable()->comment('生效结束时间');
            $table->timestamps();

            $table->comment('轮播图/Banner表');
        });

        $this->seedBannerPositionDict();
    }

    /**
     * 预置广告位字典
     */
    private function seedBannerPositionDict(): void
    {
        if (! Schema::hasTable('dicts') || ! Schema::hasTable('dict_items')) {
            return;
        }

        $dict = Dict::query()->firstOrCreate(
            ['code' => 'banner_positions'],
            ['name' => '广告位位置', 'status' => 1, 'sort' => 10, 'remark' => 'Banner 广告位配置字典']
        );

        $items = [
            ['label' => '首页顶部轮播', 'value' => 'home_top', 'label_type' => 'primary', 'sort' => 0],
            ['label' => '首页中部横幅', 'value' => 'home_banner', 'label_type' => 'success', 'sort' => 1],
            ['label' => '启动页广告', 'value' => 'launch', 'label_type' => 'warning', 'sort' => 2],
            ['label' => '用户中心', 'value' => 'user_center', 'label_type' => 'info', 'sort' => 3],
            ['label' => '发现页', 'value' => 'discover', 'label_type' => 'default', 'sort' => 4],
        ];

        foreach ($items as $item) {
            DictItem::query()->firstOrCreate(
                ['dict_id' => $dict->id, 'value' => $item['value']],
                array_merge($item, ['status' => 1])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
