<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleStatus;
use App\Enums\ScheduleTaskStatus;
use App\Http\Requests\Admin\Schedule\ScheduleIndexRequest;
use App\Http\Requests\Admin\Schedule\ScheduleSaveRequest;
use App\Http\Resources\Admin\ScheduleResource;
use App\Models\System\Schedule;
use App\Models\System\ScheduleLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * 后台定时任务管理控制器
 *
 * 提供定时任务的 CRUD、启停、手动执行等管理功能。
 * 动态任务在 AppServiceProvider::boot 中注册到 Laravel 调度器，
 * 执行日志由 ScheduleLogListener 自动记录到 schedule_logs 表。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:schedules.index')->only(['index', 'show', 'commands']);
        $this->middleware('permission:schedules.create')->only(['store']);
        $this->middleware('permission:schedules.edit')->only(['update', 'toggle']);
        $this->middleware('permission:schedules.delete')->only(['destroy']);
        $this->middleware('permission:schedules.execute')->only(['execute']);
    }

    /**
     * 定时任务列表（分页）
     */
    public function index(ScheduleIndexRequest $request): AnonymousResourceCollection
    {
        $schedules = Schedule::query()
            ->filter($request->filters())
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return ScheduleResource::collection($schedules);
    }

    /**
     * 定时任务详情
     */
    public function show(int $id): ScheduleResource
    {
        return new ScheduleResource(Schedule::query()->findOrFail($id));
    }

    /**
     * 创建定时任务
     */
    public function store(ScheduleSaveRequest $request): JsonResponse
    {
        $schedule = Schedule::create($request->validated());

        return response()->json(new ScheduleResource($schedule), 201);
    }

    /**
     * 更新定时任务
     */
    public function update(ScheduleSaveRequest $request, int $id): ScheduleResource
    {
        $schedule = Schedule::query()->findOrFail($id);
        $schedule->update($request->validated());

        return new ScheduleResource($schedule);
    }

    /**
     * 删除定时任务
     */
    public function destroy(int $id): JsonResponse
    {
        $schedule = Schedule::query()->findOrFail($id);
        $schedule->delete();

        return response()->json(status: 204);
    }

    /**
     * 切换启用/禁用状态
     */
    public function toggle(int $id): ScheduleResource
    {
        $schedule = Schedule::query()->findOrFail($id);
        $schedule->update([
            'status' => $schedule->status->isActive()
                ? ScheduleTaskStatus::INACTIVE
                : ScheduleTaskStatus::ACTIVE,
        ]);

        return new ScheduleResource($schedule);
    }

    /**
     * 手动执行定时任务
     *
     * 对于 Artisan 命令，使用 Artisan::call 同步执行并返回退出码；
     * 对于自定义 Shell 命令，使用 Symfony Process 同步执行。
     */
    public function execute(int $id): JsonResponse
    {
        $schedule = Schedule::query()->findOrFail($id);

        if ($schedule->command === 'custom') {
            return $this->executeCustomCommand($schedule);
        }

        return $this->executeArtisanCommand($schedule);
    }

    /**
     * 获取可用的 Artisan 命令列表（供前端选择）
     */
    public function commands(): JsonResponse
    {
        $commands = Artisan::all();

        $result = [];
        foreach ($commands as $name => $command) {
            if (! is_string($name) || str_starts_with($name, '_')) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'description' => $command->getDescription(),
            ];
        }

        return response()->json($result);
    }

    /**
     * 执行 Artisan 命令
     */
    protected function executeArtisanCommand(Schedule $schedule): JsonResponse
    {
        $log = $this->startManualLog($schedule, 'command');
        $startedAt = microtime(true);

        try {
            $exitCode = Artisan::call($schedule->command, $schedule->getArguments() + $schedule->getOptions());
            $output = Artisan::output();

            $log->markSuccess(microtime(true) - $startedAt, $exitCode);

            return response()->json([
                'exit_code' => $exitCode,
                'output' => $output,
                'success' => $exitCode === 0,
            ]);
        } catch (Throwable $e) {
            $log->markFailed($e);

            return response()->json([
                'exit_code' => 1,
                'output' => $e->getMessage(),
                'success' => false,
            ]);
        }
    }

    /**
     * 执行自定义 Shell 命令
     */
    protected function executeCustomCommand(Schedule $schedule): JsonResponse
    {
        $log = $this->startManualLog($schedule, 'exec');
        $startedAt = microtime(true);

        $command = $schedule->command_custom ?? '';
        $output = [];
        $exitCode = 0;

        try {
            if ($command !== '') {
                exec($command.' 2>&1', $output, $exitCode);
            }

            $log->markSuccess(microtime(true) - $startedAt, $exitCode);

            return response()->json([
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
                'success' => $exitCode === 0,
            ]);
        } catch (Throwable $e) {
            $log->markFailed($e, $exitCode);

            return response()->json([
                'exit_code' => $exitCode,
                'output' => $e->getMessage(),
                'success' => false,
            ]);
        }
    }

    /**
     * 为手动执行创建一条运行中的调度日志
     */
    protected function startManualLog(Schedule $schedule, string $type): ScheduleLog
    {
        return ScheduleLog::query()->create([
            'name' => Str::limit($schedule->name, 255, ''),
            'type' => $type,
            'expression' => $schedule->expression,
            'status' => ScheduleStatus::RUNNING,
            'hostname' => Str::limit(gethostname() ?: '', 64, ''),
            'started_at' => Carbon::now(),
        ]);
    }
}
