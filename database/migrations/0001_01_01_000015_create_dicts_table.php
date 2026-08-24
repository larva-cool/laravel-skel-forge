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
        Schema::create('dicts', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('字典ID');
            $table->string('name', 100)->comment('字典名称');
            $table->string('code', 50)->comment('字典编码');
            $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，越小越靠前');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->unique(['code'], 'uk_dict_code');

            $table->comment('字典类型表');
        });

        Schema::create('dict_items', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('字典项ID');
            $table->unsignedBigInteger('dict_id')->index()->comment('字典ID');
            $table->string('label', 100)->comment('字典项标签');
            $table->string('value', 100)->comment('字典项值');
            $table->string('label_type', 20)->default('default')->comment('标签样式');
            $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，越小越靠前');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->unique(['dict_id', 'value'], 'uk_dict_value');

            $table->comment('字典数据表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dict_items');
        Schema::dropIfExists('dicts');
    }
};
