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
     * 举报分类字典编码
     */
    private const DICT_CODE = 'report_category';

    /**
     * 举报分类项
     *
     * @var array<int, array{label: string, value: string, label_type: string, sort: int, remark: string}>
     */
    private array $items = [
        ['label' => '色情低俗', 'value' => 'pornography', 'label_type' => 'danger', 'sort' => 100, 'remark' => '色情、低俗、暴露等内容'],
        ['label' => '暴力血腥', 'value' => 'violence', 'label_type' => 'warning', 'sort' => 200, 'remark' => '暴力、血腥、恐怖等内容'],
        ['label' => '政治敏感', 'value' => 'political', 'label_type' => 'danger', 'sort' => 300, 'remark' => '涉及政治敏感话题'],
        ['label' => '广告营销', 'value' => 'ad', 'label_type' => 'info', 'sort' => 400, 'remark' => '广告、垃圾营销、引流'],
        ['label' => '辱骂人身攻击', 'value' => 'insult', 'label_type' => 'warning', 'sort' => 500, 'remark' => '人身攻击、辱骂、诽谤'],
        ['label' => '虚假信息', 'value' => 'fake', 'label_type' => 'warning', 'sort' => 600, 'remark' => '谣言、虚假信息'],
        ['label' => '侵权抄袭', 'value' => 'infringement', 'label_type' => 'info', 'sort' => 700, 'remark' => '侵犯版权、抄袭内容'],
        ['label' => '违法违规', 'value' => 'illegal', 'label_type' => 'danger', 'sort' => 800, 'remark' => '违法违规内容'],
        ['label' => '其他', 'value' => 'other', 'label_type' => 'default', 'sort' => 900, 'remark' => '其他违规情况'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('举报人用户ID');
            $table->morphs('reportable');
            $table->string('category', 32)->comment('举报分类（取自字典）');
            $table->string('reason', 500)->nullable()->comment('举报描述');
            $table->json('evidence')->nullable()->comment('证据图片/截图');
            $table->string('status', 16)->default('pending')->comment('处理状态');
            $table->unsignedBigInteger('handler_id')->nullable()->comment('处理人管理员ID');
            $table->string('process_remark', 500)->nullable()->comment('处理备注');
            $table->json('process_actions')->nullable()->comment('实际处置动作记录');
            $table->timestamp('processed_at')->nullable()->comment('处理时间');
            $table->timestamps();

            $table->index(['status', 'category', 'created_at'], 'idx_report_filter');
            $table->index(['handler_id', 'processed_at'], 'idx_report_handler');
            $table->index(['user_id', 'status'], 'idx_report_user');
            $table->comment('举报记录表');
        });

        $this->seedReportCategories();
    }

    /**
     * 注入举报分类字典
     */
    private function seedReportCategories(): void
    {
        $now = now();

        // 幂等：字典已存在则不重复创建
        $existing = DB::table('dicts')->where('code', self::DICT_CODE)->first();
        $dictId = $existing?->id;
        if ($dictId === null) {
            $dictId = DB::table('dicts')->insertGetId([
                'name' => '举报分类',
                'code' => self::DICT_CODE,
                'status' => 1,
                'sort' => 500,
                'remark' => '用户举报时可选的违规分类',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($this->items as $item) {
            $exists = DB::table('dict_items')
                ->where('dict_id', $dictId)
                ->where('value', $item['value'])
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('dict_items')->insert([
                'dict_id' => $dictId,
                'label' => $item['label'],
                'value' => $item['value'],
                'label_type' => $item['label_type'],
                'status' => 1,
                'sort' => $item['sort'],
                'remark' => $item['remark'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');

        $dict = DB::table('dicts')->where('code', self::DICT_CODE)->first();
        if ($dict !== null) {
            DB::table('dict_items')->where('dict_id', $dict->id)->delete();
            DB::table('dicts')->where('id', $dict->id)->delete();
        }
    }
};