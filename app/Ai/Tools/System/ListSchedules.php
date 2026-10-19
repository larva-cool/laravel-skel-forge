<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\System\Schedule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 定时任务定义查询工具。
 *
 * 支持按任务名称、命令、启用状态筛选，返回分页定时任务数据。只读操作。
 */
class ListSchedules implements RequiresPermission, Tool
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
        return '查询定时任务（调度任务）定义列表。支持按任务名称、命令关键字、启用状态筛选，以分页形式返回任务信息（含 Cron 表达式、执行命令、防重叠/单服务器等运行策略等）。只读操作，不能执行启用/禁用或手动触发。';
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
            'command' => $schema->string()
                ->max(100)
                ->description('模糊匹配 Artisan 命令名'),
            'status' => $schema->integer()
                ->enum([0, 1])
                ->description('状态筛选：0=禁用，1=启用'),
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

        foreach (['name', 'command'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        if ($request->has('status')) {
            $filters['status'] = $request->integer('status');
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = Schedule::query()
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Schedule $schedule) => [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'command' => $schedule->getCommandString(),
            'expression' => $schedule->expression,
            'description' => $schedule->description,
            'status' => $schedule->status->value,
            'status_label' => $schedule->status->label(),
            'without_overlapping' => $schedule->without_overlapping,
            'on_one_server' => $schedule->on_one_server,
            'run_in_background' => $schedule->run_in_background,
            'created_at' => $schedule->created_at?->toDateTimeString(),
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
