<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\System\ScheduleLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 定时任务执行日志查询工具。
 *
 * 支持按任务名称、任务类型、执行状态、执行时间范围筛选，返回分页日志数据。只读操作。
 */
class ListScheduleLogs implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'schedules.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询定时任务执行日志。支持按任务名称、任务类型（command/exec/callback）、执行状态（执行中/成功/失败/跳过）、执行时间范围筛选，以分页形式返回日志信息（含退出码、耗时、异常摘要、执行主机等）。用于排查定时任务执行失败原因。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->max(100)
                ->description('模糊匹配任务名称'),
            'type' => $schema->string()
                ->enum(['command', 'exec', 'callback'])
                ->description('任务类型筛选：command=Artisan 命令，exec=Shell 命令，callback=回调任务'),
            'status' => $schema->integer()
                ->enum([0, 1, 2, 3])
                ->description('执行状态筛选：0=执行中，1=成功，2=失败，3=跳过'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('执行起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('执行截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $filters = [];

        foreach (['name', 'type', 'start_date', 'end_date'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        if ($request->has('status')) {
            $filters['status'] = $request->integer('status');
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = ScheduleLog::query()
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (ScheduleLog $log) => [
            'id' => $log->id,
            'name' => $log->name,
            'type' => $log->type,
            'expression' => $log->expression,
            'status' => $log->status->value,
            'status_label' => $log->status->label(),
            'exit_code' => $log->exit_code,
            'runtime' => $log->runtime,
            'exception' => $log->exception ? mb_substr($log->exception, 0, 500) : null,
            'hostname' => $log->hostname,
            'started_at' => $log->started_at?->toDateTimeString(),
            'finished_at' => $log->finished_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
