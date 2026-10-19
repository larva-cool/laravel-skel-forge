<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Tasks;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Task\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 任务定义列表查询工具。
 *
 * 支持按名称/编码关键字、任务类型、启用状态、分组筛选，返回分页任务数据。只读操作。
 */
class ListTasks implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'tasks.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询任务系统中的任务定义列表。支持按名称/编码关键字、任务类型、启用状态、所属分组筛选，以分页形式返回任务信息（含奖励类型与数量、领取方式、生效时间、当前是否生效等）。只读操作。';
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
                ->description('模糊匹配任务名称或编码'),
            'type' => $schema->string()
                ->enum(['sign_in', 'complete_profile', 'follow_official_account', 'invite_user', 'daily_login', 'custom_action'])
                ->description('任务类型筛选：sign_in=签到，complete_profile=完善资料，follow_official_account=关注公众号，invite_user=邀请好友，daily_login=每日登录，custom_action=自定义行为'),
            'status' => $schema->boolean()
                ->description('启用状态筛选：true=仅启用，false=仅禁用。不传则查询全部'),
            'group_id' => $schema->integer()
                ->description('精确按所属分组 ID 筛选'),
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

        $query = Task::query()->with('group');

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($groupId = $request->integer('group_id')) {
            $query->where('group_id', $groupId);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Task $task) => [
            'id' => $task->id,
            'name' => $task->name,
            'code' => $task->code,
            'group' => $task->group ? [
                'id' => $task->group->id,
                'name' => $task->group->name,
            ] : null,
            'type' => $task->type->value,
            'type_label' => $task->type->label(),
            'description' => $task->description,
            'reward_type' => $task->reward_type->value,
            'reward_type_label' => $task->reward_type->label(),
            'reward_amount' => $task->reward_amount,
            'claim_mode' => $task->claim_mode->value,
            'claim_mode_label' => $task->claim_mode->label(),
            'status' => $task->status,
            'is_active' => $task->isActive(),
            'start_at' => $task->start_at?->toDateTimeString(),
            'end_at' => $task->end_at?->toDateTimeString(),
            'created_at' => $task->created_at?->toDateTimeString(),
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
