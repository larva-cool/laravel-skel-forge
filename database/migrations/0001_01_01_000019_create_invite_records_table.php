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
        Schema::create('invite_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id')->comment('邀请人ID');
            $table->string('inviter_type', 64)->comment('邀请人类型');
            $table->unsignedBigInteger('invitee_id')->comment('被邀请人ID');
            $table->string('invitee_type', 64)->comment('被邀请人类型');
            $table->string('description')->nullable()->comment('描述/备注');
            $table->timestamp('created_at')->nullable();

            $table->index(['inviter_id', 'inviter_type', 'created_at'], 'idx_inviter');
            $table->unique(['invitee_id', 'invitee_type'], 'uniq_invitee');
            $table->comment('邀请记录');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invite_records');
    }
};
