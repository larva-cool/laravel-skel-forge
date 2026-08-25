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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('地址ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->string('name', 100)->comment('收货人');
            $table->string('country', 2)->nullable()->default('CN')->comment('国家');
            $table->string('province')->comment('省');
            $table->string('city')->comment('市');
            $table->string('district')->comment('区县');
            $table->string('address')->comment('街道地址');
            $table->string('zipcode', 20)->nullable()->comment('邮编');
            $table->string('phone', 20)->comment('手机');
            $table->boolean('is_default')->nullable()->default(false)->comment('是否默认地址');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('收货地址表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
