<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\Task\TaskClaimMode;
use App\Enums\Task\TaskGroupType;
use App\Enums\Task\TaskResetType;
use App\Enums\Task\TaskRewardType;
use App\Enums\Task\TaskType;
use App\Models\Task\Task;
use App\Models\Task\TaskGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('task_groups') || ! Schema::hasTable('tasks')) {
            return;
        }

        // ========== 新手任务组 ==========
        $newbieGroup = TaskGroup::query()->updateOrCreate(
            ['code' => 'newbie'],
            [
                'name' => '新手任务',
                'type' => TaskGroupType::NEWBIE->value,
                'reset_type' => TaskResetType::NONE->value,
                'sort_order' => 1,
                'description' => '完成新手任务，快速熟悉平台并获得奖励',
                'status' => true,
            ]
        );

        $newbieTasks = [
            [
                'name' => '完善个人资料',
                'code' => 'complete_profile',
                'type' => TaskType::COMPLETE_PROFILE->value,
                'description' => '完善昵称、头像、性别、生日、签名、地区信息',
                'condition' => ['completeness_threshold' => 100],
                'reward_type' => TaskRewardType::COIN->value,
                'reward_amount' => 50,
                'claim_mode' => TaskClaimMode::AUTO->value,
                'sort_order' => 1,
            ],
            [
                'name' => '关注公众号',
                'code' => 'follow_official_account',
                'type' => TaskType::FOLLOW_OFFICIAL_ACCOUNT->value,
                'description' => '关注官方公众号',
                'condition' => ['target_value' => 1],
                'reward_type' => TaskRewardType::COIN->value,
                'reward_amount' => 30,
                'claim_mode' => TaskClaimMode::AUTO->value,
                'sort_order' => 2,
            ],
        ];

        foreach ($newbieTasks as $taskData) {
            Task::query()->updateOrCreate(
                ['code' => $taskData['code']],
                array_merge($taskData, [
                    'group_id' => $newbieGroup->id,
                    'status' => true,
                ])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 不回滚种子数据
    }
};
