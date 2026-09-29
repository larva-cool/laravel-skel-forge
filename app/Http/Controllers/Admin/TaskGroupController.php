<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\TaskGroup\TaskGroupIndexRequest;
use App\Http\Requests\Admin\TaskGroup\TaskGroupSaveRequest;
use App\Http\Resources\Admin\TaskGroupResource;
use App\Models\Task\TaskGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:task-groups.index')->only(['index', 'show']);
        $this->middleware('permission:task-groups.create')->only(['store']);
        $this->middleware('permission:task-groups.edit')->only(['update', 'toggleStatus']);
        $this->middleware('permission:task-groups.delete')->only(['destroy']);
    }

    /** 任务分组列表（分页） */
    public function index(TaskGroupIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);
        $query = TaskGroup::query()->withCount('tasks');

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $items = $query->orderBy('sort_order')->orderByDesc('id')->paginate($perPage);

        return TaskGroupResource::collection($items);
    }

    /** 所有分组选项（不分页，用于下拉选择） */
    public function options(): AnonymousResourceCollection
    {
        $items = TaskGroup::query()
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();

        return TaskGroupResource::collection($items);
    }

    /** 创建任务分组 */
    public function store(TaskGroupSaveRequest $request): TaskGroupResource
    {
        $group = TaskGroup::query()->create($request->validated());

        return new TaskGroupResource($group);
    }

    /** 分组详情 */
    public function show(int $id): TaskGroupResource
    {
        $group = TaskGroup::query()->with('tasks')->findOrFail($id);

        return new TaskGroupResource($group);
    }

    /** 更新任务分组 */
    public function update(TaskGroupSaveRequest $request, int $id): TaskGroupResource
    {
        $group = TaskGroup::query()->findOrFail($id);
        $group->update($request->validated());

        return new TaskGroupResource($group->fresh());
    }

    /** 切换分组启用状态 */
    public function toggleStatus(int $id): TaskGroupResource
    {
        $group = TaskGroup::query()->findOrFail($id);
        $group->update(['status' => ! $group->status]);

        return new TaskGroupResource($group->fresh());
    }

    /** 删除任务分组 */
    public function destroy(int $id): JsonResponse
    {
        $group = TaskGroup::query()->findOrFail($id);
        if ($group->tasks()->count() > 0) {
            return response()->json(['message' => __('admin.task_group_has_tasks')], 422);
        }
        $group->delete();

        return response()->json(status: 204);
    }
}
