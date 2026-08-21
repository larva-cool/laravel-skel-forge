<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ScheduleLog\ScheduleLogIndexRequest;
use App\Http\Resources\Admin\ScheduleLogResource;
use App\Models\System\ScheduleLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台调度任务日志控制器（只读）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleLogController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:schedule-logs.index')->only(['index', 'show']);
    }

    /**
     * 调度日志列表（分页）
     */
    public function index(ScheduleLogIndexRequest $request): AnonymousResourceCollection
    {
        $logs = ScheduleLog::query()
            ->filter($request->filters())
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return ScheduleLogResource::collection($logs);
    }

    /**
     * 调度日志详情
     */
    public function show(int $id): ScheduleLogResource
    {
        return new ScheduleLogResource(ScheduleLog::query()->findOrFail($id));
    }
}
