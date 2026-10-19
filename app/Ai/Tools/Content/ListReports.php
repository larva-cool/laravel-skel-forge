<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Content;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Content\Report;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 举报列表查询工具。
 *
 * 支持按处理状态、举报分类、被举报对象类型、关键字、举报时间范围筛选，
 * 返回分页举报数据。只读操作。
 */
class ListReports implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'reports.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户举报列表。支持按处理状态（待处理/处理中/已处理/已驳回）、举报分类（字典 report_category 的 value）、被举报对象类型、举报描述关键字、举报时间范围筛选，以分页形式返回举报信息（含举报人、证据、处理结果等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['pending', 'processing', 'resolved', 'rejected'])
                ->description('处理状态筛选：pending=待处理，processing=处理中，resolved=已处理，rejected=已驳回'),
            'category' => $schema->string()
                ->max(50)
                ->description('举报分类筛选（字典 report_category 的 value）'),
            'reportable_type' => $schema->string()
                ->max(50)
                ->description('被举报对象类型筛选（多态类型，如 comment 表示评论）'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配举报描述'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('举报起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('举报截止日期（YYYY-MM-DD），含当天'),
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

        $query = Report::query()->with(['user', 'handler']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($reportableType = $request->string('reportable_type')->toString()) {
            $query->where('reportable_type', $reportableType);
        }

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('reason', 'like', "%{$keyword}%");
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

        $items = $paginator->getCollection()->map(fn (Report $report) => [
            'id' => $report->id,
            'reporter' => $report->user ? [
                'id' => $report->user->id,
                'username' => $report->user->username,
                'name' => $report->user->name,
            ] : null,
            'reportable_type' => $report->reportable_type,
            'reportable_id' => $report->reportable_id,
            'category' => $report->category,
            'reason' => $report->reason,
            'evidence' => $report->evidence,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'handler' => $report->handler ? [
                'id' => $report->handler->id,
                'name' => $report->handler->name,
            ] : null,
            'process_remark' => $report->process_remark,
            'process_actions' => $report->process_actions,
            'processed_at' => $report->processed_at?->toDateTimeString(),
            'created_at' => $report->created_at?->toDateTimeString(),
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
