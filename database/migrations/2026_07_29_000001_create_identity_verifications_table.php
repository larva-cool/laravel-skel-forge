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
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id()->comment('认证记录ID');
            $table->string('verifiable_type', 100)->comment('申请人类型（多态）');
            $table->unsignedBigInteger('verifiable_id')->comment('申请人ID');
            $table->index(['verifiable_type', 'verifiable_id']);
            $table->string('type', 16)->index()->comment('认证类型：personal 个人 / enterprise 企业');

            // 审核状态（复用 ReviewStatus：pending / review / approved / rejected）
            $table->string('status', 16)->default('pending')->index()->comment('审核状态');

            // 个人字段
            $table->string('real_name', 64)->nullable()->comment('真实姓名');
            $table->string('id_card_no', 32)->nullable()->comment('身份证号（密文存储）');
            $table->string('id_card_front')->nullable()->comment('身份证人像面图片路径');
            $table->string('id_card_back')->nullable()->comment('身份证国徽面图片路径');
            $table->string('handheld')->nullable()->comment('手持身份证图片路径');

            // 企业字段
            $table->string('company_name', 128)->nullable()->comment('企业名称');
            $table->string('credit_code', 32)->nullable()->comment('统一社会信用代码');
            $table->string('legal_person_name', 64)->nullable()->comment('法人姓名');
            $table->string('legal_person_id_card_no', 32)->nullable()->comment('法人身份证号（密文存储）');
            $table->string('business_license')->nullable()->comment('营业执照图片路径');
            $table->string('opening_permit')->nullable()->comment('开户许可证图片路径');
            $table->string('bank_account', 64)->nullable()->comment('对公账户');
            $table->string('bank_name', 64)->nullable()->comment('开户行');

            // 审核字段
            $table->unsignedBigInteger('admin_id')->nullable()->index()->comment('审核管理员ID');
            $table->string('reject_reason', 32)->nullable()->comment('驳回原因（字典 identity_reject_reason 的 value）');
            $table->text('reject_remark')->nullable()->comment('驳回备注');
            $table->timestamp('submitted_at')->nullable()->comment('提交时间');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->timestamp('expires_at')->nullable()->comment('认证有效期');

            $table->timestamps();

            $table->comment('实名认证审核记录表');
        });

        $this->seedRejectReasonDict();
    }

    /**
     * 预置驳回原因字典
     */
    private function seedRejectReasonDict(): void
    {
        if (! Schema::hasTable('dicts') || ! Schema::hasTable('dict_items')) {
            return;
        }

        $dict = Dict::query()->firstOrCreate(
            ['code' => 'identity_reject_reason'],
            ['name' => '实名认证驳回原因', 'status' => 1, 'sort' => 0, 'remark' => '实名认证模块使用的驳回原因字典']
        );

        $items = [
            ['label' => '证件模糊不清', 'value' => 'id_blurry', 'label_type' => 'warning', 'sort' => 0],
            ['label' => '证件信息不符', 'value' => 'mismatch', 'label_type' => 'danger', 'sort' => 1],
            ['label' => '证件已过期', 'value' => 'expired', 'label_type' => 'info', 'sort' => 2],
            ['label' => '证件无效', 'value' => 'invalid', 'label_type' => 'danger', 'sort' => 3],
            ['label' => '证件资料不完整', 'value' => 'incomplete', 'label_type' => 'warning', 'sort' => 4],
            ['label' => '其他原因', 'value' => 'other', 'label_type' => 'info', 'sort' => 5],
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
        Schema::dropIfExists('identity_verifications');
        if (Schema::hasTable('dicts') && Schema::hasTable('dict_items')) {
            Dict::query()->where('code', 'identity_reject_reason')->delete();
        }
    }
};
