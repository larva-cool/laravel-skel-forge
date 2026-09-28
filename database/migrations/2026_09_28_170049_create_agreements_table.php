<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\DictLabelType;
use App\Models\System\Dict;
use App\Models\System\DictItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 协议类别字典编码
     */
    private const string AGREEMENT_CATEGORY_CODE = 'agreement_category';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('协议ID');
            $table->string('title', 200)->comment('协议标题');
            $table->string('category', 50)->comment('协议类别（字典值）');
            $table->longText('content')->comment('协议内容（富文本 HTML）');
            $table->string('version', 20)->comment('版本号');
            $table->string('status', 20)->default('draft')->comment('状态：draft草稿 published已发布 archived已归档');
            $table->timestamp('effective_at')->nullable()->comment('生效时间');
            $table->unsignedInteger('sort')->default(1000)->comment('排序权重，越小越靠前');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('创建/编辑管理员ID');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status']);
            $table->index('admin_id');

            $table->comment('用户协议表');
        });

        $this->seedAgreementCategoryDict();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }

    /**
     * Seed 协议类别字典与默认字典项
     */
    private function seedAgreementCategoryDict(): void
    {
        // 仅在 dicts 表存在时执行（单元测试或全新环境）
        if (! Schema::hasTable('dicts') || ! Schema::hasTable('dict_items')) {
            return;
        }

        $now = now();

        // upsert 字典类型
        $dict = Dict::query()->where('code', self::AGREEMENT_CATEGORY_CODE)->first();
        if (! $dict) {
            $dict = new Dict;
            $dict->code = self::AGREEMENT_CATEGORY_CODE;
        }
        $dict->name = '协议类别';
        $dict->status = 1;
        $dict->sort = 100;
        $dict->remark = '用于管理用户协议、隐私政策等协议内容的分类';
        $dict->save();

        $items = [
            ['value' => 'user_agreement', 'label' => '用户协议', 'label_type' => DictLabelType::PRIMARY, 'sort' => 100],
            ['value' => 'privacy_policy', 'label' => '隐私政策', 'label_type' => DictLabelType::INFO, 'sort' => 200],
        ];

        foreach ($items as $item) {
            $itemModel = DictItem::query()
                ->where('dict_id', $dict->id)
                ->where('value', $item['value'])
                ->first();
            if (! $itemModel) {
                $itemModel = new DictItem;
                $itemModel->dict_id = $dict->id;
                $itemModel->value = $item['value'];
            }
            $itemModel->label = $item['label'];
            $itemModel->label_type = $item['label_type'];
            $itemModel->sort = $item['sort'];
            $itemModel->status = 1;
            $itemModel->remark = null;
            $itemModel->save();
        }

        // 清理字典缓存，确保前端可立即读取
        DB::table('cache')->where('key', 'like', '%agreement_category%')->delete();
    }
};
