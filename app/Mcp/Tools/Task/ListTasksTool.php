<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Task;

use App\Http\Resources\Api\V1\TaskGroupResource;
use App\Services\TaskService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取任务中心列表（按分组返回任务及当前用户进度）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('task.list')]
#[Description('获取任务中心列表，按分组返回任务及当前登录用户的完成进度。')]
#[IsReadOnly]
#[IsIdempotent]
class ListTasksTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TaskService $taskService): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $groups = $taskService->getUserTaskList($user);

        return Response::structured([
            'items' => TaskGroupResource::collection($groups)->resolve(),
        ]);
    }
}
