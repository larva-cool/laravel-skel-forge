<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Task;

use App\Models\Task\Task;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 领取任务奖励
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('task.claim')]
#[Description('领取指定任务的奖励；任务需在有效期内且当前用户满足领取条件。')]
class ClaimTaskTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TaskService $taskService): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'task_id' => ['required', 'integer', 'min:1'],
            'period_key' => ['sometimes', 'string', 'max:32'],
        ]);

        $task = Task::find($arguments['task_id']);
        if (! $task || ! $task->isActive()) {
            return Response::error('Task not found or inactive.');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $progress = $taskService->claimReward($user, $task->id, $arguments['period_key'] ?? 'all');

        if (! $progress) {
            return Response::error('Task reward is not claimable.');
        }

        $user->refresh();

        return Response::structured([
            'message' => 'Task reward claimed successfully.',
            'reward_type' => $task->reward_type->value,
            'reward_type_label' => $task->reward_type->label(),
            'reward_amount' => $task->reward_amount,
            'available_coins' => $user->available_coins,
            'available_points' => $user->available_points,
            'vip_expires_at' => $user->vip_expires_at?->toDateTimeString(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('任务 ID')->min(1)->required(),
            'period_key' => $schema->string()->description('可选，周期标识，默认 all')->max(32),
        ];
    }
}
