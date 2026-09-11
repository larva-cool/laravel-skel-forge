<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Enums\CoinType;
use App\Enums\Gender;
use App\Enums\PointType;
use App\Enums\Task\TaskProgressStatus;
use App\Enums\Task\TaskResetType;
use App\Enums\Task\TaskRewardType;
use App\Enums\Task\TaskType;
use App\Models\Task\Task;
use App\Models\Task\TaskGroup;
use App\Models\Task\TaskLog;
use App\Models\Task\TaskProgress;
use App\Models\User;
use App\Support\CoinHelper;
use App\Support\PointHelper;
use App\Support\UserHelper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 任务服务
 *
 * 负责任务进度更新、完成判定、奖励发放等核心业务逻辑。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class TaskService
{
    /**
     * 计算任务在当前时间点的周期标识
     *
     * - 不重置 → 'all'
     * - 每日重置 → 'Y-m-d'（如 2026-09-10）
     * - 每周重置 → 'Y-W'（ISO周，如 2026-37）
     * - 每月重置 → 'Y-m'（如 2026-09）
     */
    public function resolvePeriodKey(TaskResetType $resetType, ?Carbon $time = null): string
    {
        $time = $time ?: now();

        return match ($resetType) {
            TaskResetType::NONE => 'all',
            TaskResetType::DAILY => $time->format('Y-m-d'),
            TaskResetType::WEEKLY => $time->format('o-W'),
            TaskResetType::MONTHLY => $time->format('Y-m'),
        };
    }

    /**
     * 计算用户资料完整度百分比
     *
     * 6个维度各占1/6：昵称、头像、性别、生日、签名、地区（省市区三级联动）
     *
     * @return int 完整度百分比（0-100）
     */
    public function calculateProfileCompleteness(User $user): int
    {
        $filled = 0;

        // 1. 昵称：必须数据库原始值非 null（getter 有 fallback，需用 getRawOriginal）
        if ($user->getRawOriginal('name') !== null) {
            $filled++;
        }

        // 2. 头像
        if ($user->avatar !== null) {
            $filled++;
        }

        // 确保 profile 关系已加载且存在
        $profile = $user->profile;
        if ($profile) {
            // 3. 性别：gender != GENDER_UNKNOWN（保密即未选择）
            if ($profile->gender !== Gender::GENDER_UNKNOWN) {
                $filled++;
            }

            // 4. 生日
            if ($profile->birthday !== null) {
                $filled++;
            }

            // 5. 签名
            if ($profile->bio !== null && trim($profile->bio) !== '') {
                $filled++;
            }

            // 6. 地区：省/市/区三级联动都非空
            if ($profile->province_id !== null && $profile->city_id !== null && $profile->district_id !== null) {
                $filled++;
            }
        }

        return (int) round($filled / 6 * 100);
    }

    /**
     * 更新用户某类型任务的进度（通用累加方式）
     *
     * 适用于：邀请好友、每日登录、自定义行为等数值累计类任务。
     * 对于签到任务（sign_in）请使用 handleSignIn() 方法。
     *
     * @param  User  $user  用户模型
     * @param  TaskType  $taskType  任务类型
     * @param  int  $increment  进度增量，默认 1
     * @param  string|null  $periodKey  手动指定周期 key，默认按任务重置类型自动计算
     * @param  string|null  $sourceType  触发来源（事件类名等）
     * @return TaskProgress[] 受影响的进度记录
     */
    public function updateProgress(User $user, TaskType $taskType, int $increment = 1, ?string $periodKey = null, ?string $sourceType = null): array
    {
        if ($increment <= 0) {
            return [];
        }

        $tasks = Task::where('type', $taskType)
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $updated = [];
        foreach ($tasks as $task) {
            $pk = $periodKey ?? $this->resolvePeriodKey($task->group->reset_type ?? TaskResetType::NONE);
            $result = $this->incrementAndCheck($user, $task, $pk, $increment, $sourceType);
            if ($result) {
                $updated[] = $result;
            }
        }

        return $updated;
    }

    /**
     * 更新资料完整度类型任务
     *
     * 直接设置 current_value 为完整度百分比（0-100），不做累加。
     *
     * @return TaskProgress[]
     */
    public function updateProfileCompleteness(User $user, int $completeness, ?string $sourceType = null): array
    {
        $tasks = Task::where('type', TaskType::COMPLETE_PROFILE)
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $updated = [];
        foreach ($tasks as $task) {
            $pk = $this->resolvePeriodKey($task->group->reset_type ?? TaskResetType::NONE);
            $result = $this->setProgressAndCheck($user, $task, $pk, $completeness, $sourceType);
            if ($result) {
                $updated[] = $result;
            }
        }

        return $updated;
    }

    /**
     * 处理签到任务（特殊逻辑：按 serial_days 精确匹配）
     *
     * @param  User  $user  用户模型
     * @param  int  $serialDays  连续签到天数
     * @param  string|null  $sourceType  触发来源
     * @return TaskProgress[] 受影响的进度记录
     */
    public function handleSignIn(User $user, int $serialDays, ?string $sourceType = null): array
    {
        $tasks = Task::where('type', TaskType::SIGN_IN)
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $updated = [];
        foreach ($tasks as $task) {
            $condition = $task->condition;
            $targetDays = (int) ($condition['serial_days'] ?? 0);

            if ($targetDays === 0) {
                // 每日签到基础奖：按日期周期，current_value 直接设为 1（当天签了到）
                $pk = now()->format('Y-m-d');
                $result = $this->setProgressAndCheck($user, $task, $pk, 1, $sourceType);
            } elseif ($targetDays === $serialDays) {
                // 连续签到阶梯奖：精确匹配 serial_days，period_key 永久唯一
                $pk = "sign_in_day_{$targetDays}";
                $result = $this->setProgressAndCheck($user, $task, $pk, $serialDays, $sourceType);
            } else {
                continue;
            }

            if ($result) {
                $updated[] = $result;
            }
        }

        return $updated;
    }

    /**
     * 用户手动领取奖励
     */
    public function claimReward(User $user, int $taskId, string $periodKey = 'all'): ?TaskProgress
    {
        $progress = $this->getProgressWithLock($user->id, $taskId, $periodKey);
        if (! $progress || $progress->status !== TaskProgressStatus::COMPLETED) {
            return null;
        }

        $task = $progress->task;
        if (! $task) {
            return null;
        }

        try {
            DB::transaction(function () use ($progress, $task, $user) {
                $this->grantReward($user, $task, $progress);
            });

            return $progress->fresh();
        } catch (UniqueConstraintViolationException) {
            return $progress->fresh();
        }
    }

    /**
     * 获取用户在指定周期内的任务列表（含进度），按分组返回
     *
     * @return Collection<int, TaskGroup> 含 tasks 和每个 task 的 progress
     */
    public function getUserTaskList(User $user, ?Carbon $time = null): Collection
    {
        $time = $time ?: now();
        $groups = TaskGroup::where('status', true)
            ->with('activeTasks')
            ->orderBy('sort_order')
            ->get();

        foreach ($groups as $group) {
            foreach ($group->activeTasks as $task) {
                $pk = $this->resolvePeriodKey($group->reset_type, $time);

                // 对于签到阶梯任务，不需要附加到列表中（签到页面单独处理）
                // 这里返回所有启用中的任务
                $progress = $user->taskProgresses()
                    ->where('task_id', $task->id)
                    ->where('period_key', $pk)
                    ->first();

                $task->setAttribute('user_progress', $progress);
                $task->setAttribute('period_key', $pk);
            }
        }

        return $groups;
    }

    /**
     * 累加进度值并判定是否达成目标
     */
    protected function incrementAndCheck(User $user, Task $task, string $periodKey, int $increment, ?string $sourceType): ?TaskProgress
    {
        return DB::transaction(function () use ($user, $task, $periodKey, $increment, $sourceType) {
            $progress = $this->getOrCreateProgress($user->id, $task->id, $periodKey);

            // 已完成或已领取则跳过
            if ($progress->isCompleted()) {
                return null;
            }

            $progress->current_value += $increment;

            $target = $this->getTargetValue($task);
            if ($progress->current_value >= $target) {
                $progress->current_value = $target;
                $progress->markCompleted();
                $progress->save();

                if ($task->isAutoClaim()) {
                    $this->grantReward($user, $task, $progress, $sourceType);
                }
            } else {
                $progress->save();
            }

            return $progress;
        });
    }

    /**
     * 直接设置进度值并判定
     */
    protected function setProgressAndCheck(User $user, Task $task, string $periodKey, int $value, ?string $sourceType): ?TaskProgress
    {
        return DB::transaction(function () use ($user, $task, $periodKey, $value, $sourceType) {
            $progress = $this->getOrCreateProgress($user->id, $task->id, $periodKey);

            if ($progress->isCompleted()) {
                return null;
            }

            // 只在新值更大时更新（防止资料回退导致进度倒退）
            if ($value <= $progress->current_value) {
                return null;
            }

            $progress->current_value = $value;

            $target = $this->getTargetValue($task);
            if ($progress->current_value >= $target) {
                $progress->current_value = $target;
                $progress->markCompleted();
                $progress->save();

                if ($task->isAutoClaim()) {
                    $this->grantReward($user, $task, $progress, $sourceType);
                }
            } else {
                $progress->save();
            }

            return $progress;
        });
    }

    /**
     * 从任务配置中获取目标值
     */
    protected function getTargetValue(Task $task): int
    {
        $condition = $task->condition;

        // 优先使用完整度阈值（完善资料类）
        if ($task->type === TaskType::COMPLETE_PROFILE) {
            return (int) ($condition['completeness_threshold'] ?? 100);
        }

        // 签到阶梯任务目标为 serial_days
        if ($task->type === TaskType::SIGN_IN && (int) ($condition['serial_days'] ?? 0) > 0) {
            return (int) $condition['serial_days'];
        }

        // 每日签到 base 任务目标为 1
        if ($task->type === TaskType::SIGN_IN) {
            return 1;
        }

        // 通用目标值
        return (int) ($condition['target_value'] ?? 1);
    }

    /**
     * 获取或创建进度记录（含行锁）
     */
    protected function getOrCreateProgress(int $userId, int $taskId, string $periodKey): TaskProgress
    {
        $progress = TaskProgress::where('user_id', $userId)
            ->where('task_id', $taskId)
            ->where('period_key', $periodKey)
            ->first();

        if ($progress) {
            return $progress;
        }

        try {
            return TaskProgress::create([
                'user_id' => $userId,
                'task_id' => $taskId,
                'period_key' => $periodKey,
                'current_value' => 0,
                'status' => TaskProgressStatus::IN_PROGRESS,
            ]);
        } catch (UniqueConstraintViolationException) {
            return TaskProgress::where('user_id', $userId)
                ->where('task_id', $taskId)
                ->where('period_key', $periodKey)
                ->first();
        }
    }

    /**
     * 使用行锁获取进度记录
     */
    protected function getProgressWithLock(int $userId, int $taskId, string $periodKey): ?TaskProgress
    {
        return TaskProgress::where('user_id', $userId)
            ->where('task_id', $taskId)
            ->where('period_key', $periodKey)
            ->lockForUpdate()
            ->first();
    }

    /**
     * 发放奖励（在事务内调用）
     */
    protected function grantReward(User $user, Task $task, TaskProgress $progress, ?string $sourceType = null): void
    {
        $rewardType = $task->reward_type;
        $amount = $task->reward_amount;

        if ($amount <= 0) {
            return;
        }

        // 写入日志
        TaskLog::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'period_key' => $progress->period_key,
            'reward_type' => $rewardType,
            'reward_amount' => $amount,
            'source_type' => $sourceType,
            'remark' => "完成任务「{$task->name}」获得奖励",
        ]);

        // 调用对应 Helper 发放
        match ($rewardType) {
            TaskRewardType::COIN => CoinHelper::incr(
                $user, $amount, $task, CoinType::TYPE_TASK, "任务奖励：{$task->name}"
            ),
            TaskRewardType::POINT => PointHelper::incr(
                $user->id, $amount, $task, PointType::TYPE_TASK, "任务奖励：{$task->name}"
            ),
            TaskRewardType::VIP_DAYS => UserHelper::extendVip($user, $amount),
        };

        // 标记为已领取
        $progress->markRewarded();
    }
}
