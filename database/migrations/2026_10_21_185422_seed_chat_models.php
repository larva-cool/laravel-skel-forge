<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Models\Ai\ChatModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('chat_models')) {
            return;
        }

        $models = [
            ['model_id' => 'deepseek-v4-1-flash-economy-261017', 'name' => 'DeepSeek-V4.1-Flash', 'is_default' => true, 'sort' => 1],
            ['model_id' => 'doubao-seed-2-1-lite-260915', 'name' => 'Doubao-Seed-2.1-lite', 'is_default' => false, 'sort' => 2],
            ['model_id' => 'glm-5-3-flash-260828', 'name' => 'GLM-5.3-Flash', 'is_default' => false, 'sort' => 3],
            ['model_id' => 'doubao-seed-evolving', 'name' => 'Doubao-Seed-Evolving', 'is_default' => false, 'sort' => 4],
        ];

        foreach ($models as $model) {
            ChatModel::query()->firstOrCreate(
                ['provider' => 'volc', 'model_id' => $model['model_id']],
                [
                    'name' => $model['name'],
                    'is_default' => $model['is_default'],
                    'is_enabled' => true,
                    'sort' => $model['sort'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 保留数据，不回滚
    }
};
