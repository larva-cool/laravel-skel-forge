<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\Task\TaskProgressStatus;
use App\Models\Task\TaskLog;
use App\Models\Task\TaskProgress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 用户任务进度能力
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait HasTaskProgress
{
    public function taskProgresses(): HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }

    public function taskLogs(): HasMany
    {
        return $this->hasMany(TaskLog::class);
    }

    /**
     * 获取或创建指定任务在指定周期的进度记录
     */
    public function getTaskProgress(int $taskId, string $periodKey = 'all'): TaskProgress
    {
        return $this->taskProgresses()->firstOrCreate([
            'task_id' => $taskId,
            'period_key' => $periodKey,
        ], [
            'current_value' => 0,
            'status' => TaskProgressStatus::IN_PROGRESS,
        ]);
    }
}
