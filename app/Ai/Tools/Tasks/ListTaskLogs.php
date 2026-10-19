<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Tasks;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Task\TaskLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 任务奖励发放日志查询工具。
 *
 * 支持按关键字、任务、奖励类型、用户、时间范围筛选，返回分页日志数据。只读操作。
 */
class ListTaskLogs implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'task-logs.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询任务完成与奖励发放日志。支持按关键字（任务名称/编码、用户名、周期标识、备注）、任务 ID、奖励类型、用户 ID、发放时间范围筛选，以分页形式返回日志信息（含奖励类型与数量、触发来源等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配任务名称/编码、用户名、周期标识、触发来源或备注'),
            'task_id' => $schema->integer()
                ->description('精确按任务 ID 筛选'),
            'user_id' => $schema->integer()
                ->description('精确按用户 ID 筛选'),
            'reward_type' => $schema->string()
                ->enum(['coin', 'point', 'vip_days'])
                ->description('奖励类型筛选：coin=金币，point=积分，vip_days=VIP天数'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('发放起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('发放截止日期（YYYY-MM-DD），含当天'),
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

        $query = TaskLog::query()->with(['task', 'user']);

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('period_key', 'like', "%{$keyword}%")
                    ->orWhere('source_type', 'like', "%{$keyword}%")
                    ->orWhere('remark', 'like', "%{$keyword}%")
                    ->orWhereHas('task', fn ($tq) => $tq->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%"))
                    ->orWhereHas('user', fn ($uq) => $uq->where('username', 'like', "%{$keyword}%"));
            });
        }

        if ($taskId = $request->integer('task_id')) {
            $query->where('task_id', $taskId);
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($rewardType = $request->string('reward_type')->toString()) {
            $query->where('reward_type', $rewardType);
        }

        if ($startDate = $request->string('start_date')->toString()) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->string('end_date')->toString()) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (TaskLog $log) => [
            'id' => $log->id,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'username' => $log->user->username,
                'name' => $log->user->name,
            ] : null,
            'task' => $log->task ? [
                'id' => $log->task->id,
                'name' => $log->task->name,
                'code' => $log->task->code,
            ] : null,
            'period_key' => $log->period_key,
            'reward_type' => $log->reward_type->value,
            'reward_type_label' => $log->reward_type->label(),
            'reward_amount' => $log->reward_amount,
            'source_type' => $log->source_type,
            'remark' => $log->remark,
            'created_at' => $log->created_at?->toDateTimeString(),
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
