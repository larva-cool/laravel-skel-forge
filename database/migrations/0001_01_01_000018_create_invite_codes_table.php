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
        Schema::create('invite_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 26)->comment('邀请码');
            $table->unsignedBigInteger('owner_id')->comment('所属主体ID');
            $table->string('owner_type', 64)->comment('所属主体类型');
            $table->timestamps();

            $table->unique('code', 'uniq_code');
            $table->unique(['owner_id', 'owner_type'], 'uniq_owner');
            $table->comment('邀请码');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invite_codes');
    }
};
