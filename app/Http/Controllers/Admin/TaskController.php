<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Task\TaskIndexRequest;
use App\Http\Requests\Admin\Task\TaskSaveRequest;
use App\Http\Resources\Admin\TaskResource;
use App\Models\Task\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:tasks.index')->only(['index', 'show']);
        $this->middleware('permission:tasks.create')->only(['store']);
        $this->middleware('permission:tasks.edit')->only(['update', 'toggleStatus']);
        $this->middleware('permission:tasks.delete')->only(['destroy']);
    }

    /** 任务列表（分页） */
    public function index(TaskIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);
        $query = Task::query()->with('group');

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $items = $query->orderBy('sort_order')->orderByDesc('id')->paginate($perPage);

        return TaskResource::collection($items);
    }

    /** 创建任务 */
    public function store(TaskSaveRequest $request): TaskResource
    {
        $task = Task::query()->create($request->validated());

        return new TaskResource($task->load('group'));
    }

    /** 任务详情 */
    public function show(int $id): TaskResource
    {
        $task = Task::query()->with('group')->findOrFail($id);

        return new TaskResource($task);
    }

    /** 更新任务 */
    public function update(TaskSaveRequest $request, int $id): TaskResource
    {
        $task = Task::query()->findOrFail($id);
        $task->update($request->validated());

        return new TaskResource($task->fresh()->load('group'));
    }

    /** 切换任务启用状态 */
    public function toggleStatus(int $id): TaskResource
    {
        $task = Task::query()->findOrFail($id);
        $task->update(['status' => ! $task->status]);

        return new TaskResource($task->fresh()->load('group'));
    }

    /** 删除任务 */
    public function destroy(int $id): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $task->delete();

        return response()->json(status: 204);
    }
}
