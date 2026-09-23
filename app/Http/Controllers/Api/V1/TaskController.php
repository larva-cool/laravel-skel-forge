<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskGroupResource;
use App\Models\Task\Task;
use App\Services\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * 任务中心控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * 获取任务中心（按分组返回任务列表及用户进度）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $groups = $this->taskService->getUserTaskList($user);

        return TaskGroupResource::collection($groups);
    }

    /**
     * 领取任务奖励
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function claim(Request $request, int $taskId): JsonResponse
    {
        $user = $request->user();

        $task = Task::find($taskId);
        if (! $task || ! $task->isActive()) {
            throw ValidationException::withMessages([
                'task_id' => '任务不存在或已失效',
            ]);
        }

        $periodKey = $request->input('period_key', 'all');

        $progress = $this->taskService->claimReward($user, $taskId, $periodKey);

        if (! $progress) {
            throw ValidationException::withMessages([
                'task_id' => '任务未完成或已领取',
            ]);
        }

        $user->refresh();

        return response()->json([
            'message' => '领取成功',
            'reward_type' => $task->reward_type->value,
            'reward_type_label' => $task->reward_type->label(),
            'reward_amount' => $task->reward_amount,
            'available_coins' => $user->available_coins,
            'available_points' => $user->available_points,
            'vip_expires_at' => $user->vip_expires_at?->toDateTimeString(),
        ]);
    }
}
