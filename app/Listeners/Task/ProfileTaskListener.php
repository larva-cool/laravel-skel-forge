<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Listeners\Task;

use App\Events\User\UserProfileUpdated;
use App\Services\TaskService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 完善资料任务监听器
 *
 * 监听用户资料更新事件，重新计算资料完整度并更新任务进度。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ProfileTaskListener implements ShouldQueue
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {
        //
    }

    public function handle(UserProfileUpdated $event): void
    {
        // 确保 profile 关系已加载，用于完整度计算
        $user = $event->user->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($user);

        $this->taskService->updateProfileCompleteness($user, $completeness, self::class);
    }
}
