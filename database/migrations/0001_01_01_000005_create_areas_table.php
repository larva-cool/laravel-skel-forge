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
        Schema::create('areas', function (Blueprint $table) {
            $table->id()->comment('区域编码');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父地区');
            $table->string('name', 50)->comment('地区名称');
            $table->unsignedInteger('area_code')->nullable()->comment('地区编码');
            $table->float('lat', 10, 6)->nullable()->comment('纬度');
            $table->float('lng', 10, 6)->nullable()->comment('经度');
            $table->string('city_code')->nullable()->comment('城市编码');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，越小越靠前');
            $table->timestamps();
            $table->softDeletes()->comment('删除时间');
            $table->index(['parent_id', 'deleted_at', 'sort', 'id'], 'idx_parent_id');
            $table->index(['name', 'deleted_at'], 'idx_name');
            $table->index(['area_code', 'deleted_at'], 'idx_area_code');

            $table->comment('地区表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
