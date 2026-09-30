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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('source_type', 255)->comment('源类型');
            $table->unsignedBigInteger('source_id')->comment('源ID');
            $table->ipAddress('ip')->nullable()->comment('访问IP');
            $table->timestamp('created_at')->nullable()->useCurrent()->comment('创建时间');

            $table->index(['source_type', 'source_id'], 'idx_visit_source');
            $table->index(['user_id', 'source_type', 'source_id'], 'idx_visit_user_source');
            $table->comment('访问记录');
        });

        if (DB::connection()->getConfig('driver') == 'mysql') {
            $tablePrefix = DB::connection()->getTablePrefix();
            DB::statement('ALTER TABLE `'.$tablePrefix.'visits` MODIFY COLUMN `ip` VARBINARY(16)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
