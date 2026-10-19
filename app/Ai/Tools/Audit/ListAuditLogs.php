<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Audit;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\System\AuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 后台操作日志查询工具。
 *
 * 支持按操作人、路由、请求方法、请求路径、是否失败、时间范围筛选，
 * 用于审计管理员操作行为、排查误操作。只读操作。
 */
class ListAuditLogs implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'audit-logs.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询后台管理员操作日志（谁在什么时间对哪个接口做了什么操作、是否成功）。支持按操作人名称、路由名称、请求方法、请求路径、是否失败、操作时间范围筛选。用于审计管理员操作行为、排查误操作。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operator_name' => $schema->string()
                ->max(50)
                ->description('模糊匹配操作人名称'),
            'route' => $schema->string()
                ->max(100)
                ->description('模糊匹配路由名称，如 admin.users.update'),
            'method' => $schema->string()
                ->enum(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
                ->description('请求方法筛选'),
            'uri' => $schema->string()
                ->max(100)
                ->description('模糊匹配请求路径，如 admin/users'),
            'is_failed' => $schema->boolean()
                ->description('操作结果筛选：true=仅失败（响应状态码>=400），false=仅成功。不传则查询全部'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('操作起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('操作截止日期（YYYY-MM-DD），含当天'),
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

        foreach (['operator_name', 'route', 'method', 'uri'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        if ($request->has('is_failed')) {
            $filters['is_failed'] = $request->boolean('is_failed');
        }

        if ($startDate = $request->string('start_date')->toString()) {
            $filters['start_date'] = $startDate;
        }
        if ($endDate = $request->string('end_date')->toString()) {
            $filters['end_date'] = $endDate;
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = AuditLog::query()
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'operator_name' => $log->operator_name,
            'title' => $log->title,
            'route' => $log->route,
            'method' => $log->method,
            'uri' => $log->uri,
            'status_code' => $log->status_code,
            'is_failed' => $log->isFailed(),
            'error' => $log->error,
            'runtime' => $log->runtime,
            'ip' => $log->ip,
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
