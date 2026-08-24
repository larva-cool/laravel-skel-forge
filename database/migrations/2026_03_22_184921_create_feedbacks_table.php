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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('反馈ID');
            $table->string('code', 32)->unique('uk_feedback_code')->comment('反馈编号');
            $table->string('title', 200)->comment('标题');
            $table->text('content')->comment('详细描述');
            $table->json('images')->nullable()->comment('反馈图片列表（URL 数组）');
            $table->string('type', 50)->index()->comment('反馈类型（字典 feedback_type 的 value）');
            $table->tinyInteger('status')->default(0)->index()->comment('状态：0待处理 1分析中 2已规划 3处理中 4已解决 5已关闭');
            $table->tinyInteger('priority')->default(1)->index()->comment('优先级：1低 2中 3高 4紧急');
            $table->string('contact_name', 50)->nullable()->comment('联系人');
            $table->string('contact_info', 100)->nullable()->comment('联系方式');
            $table->string('user_agent', 500)->nullable()->comment('浏览器UA');
            $table->string('ip_address', 45)->nullable()->comment('提交IP');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('关联用户ID');
            $table->unsignedBigInteger('admin_id')->nullable()->index()->comment('处理人ID');
            $table->text('admin_remark')->nullable()->comment('管理员处理备注');
            $table->timestamp('resolved_at')->nullable()->comment('解决时间');
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');
            $table->timestamps();

            $table->comment('用户反馈表');
        });

        $this->seedFeedbackTypeDict();
    }

    /**
     * 预置反馈类型字典
     */
    private function seedFeedbackTypeDict(): void
    {
        if (! Schema::hasTable('dicts') || ! Schema::hasTable('dict_items')) {
            return;
        }

        $dict = Dict::query()->firstOrCreate(
            ['code' => 'feedback_type'],
            ['name' => '反馈类型', 'status' => 1, 'sort' => 0, 'remark' => '用户反馈模块使用的反馈类型字典']
        );

        $items = [
            ['label' => '功能建议', 'value' => 'feature', 'label_type' => 'primary', 'sort' => 0],
            ['label' => '体验问题', 'value' => 'experience', 'label_type' => 'warning', 'sort' => 1],
            ['label' => 'Bug 反馈', 'value' => 'bug', 'label_type' => 'danger', 'sort' => 2],
            ['label' => '其他', 'value' => 'other', 'label_type' => 'info', 'sort' => 3],
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
        Schema::dropIfExists('feedbacks');
    }
};
