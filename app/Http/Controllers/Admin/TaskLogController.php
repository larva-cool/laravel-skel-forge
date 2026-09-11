<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaskLog\TaskLogIndexRequest;
use App\Http\Resources\Admin\TaskLogResource;
use App\Models\Task\TaskLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台任务完成日志控制器
 */
class TaskLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 任务日志列表（分页）
     */
    public function index(TaskLogIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $query = TaskLog::with(['task', 'user'])
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->string('keyword');
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('period_key', 'like', "%{$keyword}%")
                        ->orWhere('source_type', 'like', "%{$keyword}%")
                        ->orWhere('remark', 'like', "%{$keyword}%")
                        ->orWhereHas('task', fn ($tq) => $tq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('code', 'like', "%{$keyword}%"))
                        ->orWhereHas('user', fn ($uq) => $uq->where('username', 'like', "%{$keyword}%"));
                    if (is_numeric((string) $keyword)) {
                        $sub->orWhere('user_id', (int) $keyword);
                    }
                });
            })
            ->when($request->filled('task_id'), fn ($q) => $q->where('task_id', $request->integer('task_id')))
            ->when($request->filled('reward_type'), fn ($q) => $q->where('reward_type', $request->string('reward_type')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('end_date')))
            ->orderByDesc('id');

        return TaskLogResource::collection($query->paginate($perPage));
    }

    /**
     * 日志详情
     */
    public function show(int $id): JsonResponse
    {
        $log = TaskLog::with(['task', 'user'])->findOrFail($id);

        return response()->json(new TaskLogResource($log));
    }
}
