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
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('source_type')->comment('源类型');
            $table->unsignedBigInteger('source_id')->comment('源ID');
            $table->json('extra')->nullable()->comment('扩展信息');
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_id'], 'uniq2_user_follow');
            $table->comment('关注');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
