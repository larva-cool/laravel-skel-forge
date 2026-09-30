<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Exports\Admin\ReportListExport;
use App\Http\Requests\Admin\Report\ReportProcessRequest;
use App\Http\Resources\Admin\ReportResource;
use App\Http\Resources\Admin\ReportStatResource;
use App\Models\Content\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 后台举报管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportController extends Controller
{
    use \App\Exports\HandlesExports;

    public function __construct(private readonly ReportService $reportService)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:reports.index')->only(['index', 'show', 'stats', 'export']);
        $this->middleware('permission:reports.edit')->only(['update']);
    }

    /**
     * 举报列表（分页，支持按状态/分类/对象类型/时间/关键词过滤）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = $this->reportService->listQuery($this->reportFilters($request))
            ->paginate(per_page($request));

        return ReportResource::collection($items);
    }

    /**
     * 举报统计数据
     */
    public function stats(): ReportStatResource
    {
        return new ReportStatResource(ReportStatResource::makeStats());
    }

    /**
     * 举报详情
     */
    public function show(int $id): ReportResource
    {
        $report = Report::query()
            ->with(['user', 'handler', 'reportable'])
            ->findOrFail($id);

        return new ReportResource($report);
    }

    /**
     * 处理举报（状态流转 + 联动处置）
     */
    public function update(ReportProcessRequest $request, int $id): ReportResource
    {
        $report = Report::query()->findOrFail($id);

        if ($report->status->isClosed()) {
            abort(422, '该举报已结案，不能再次处理');
        }

        // 仅允许 resolved / rejected（不允许 pending / processing 通过此端点写回）
        $newStatus = ReportStatus::tryFrom($request->validated()['status']);
        if (! $newStatus || ! in_array($newStatus, [ReportStatus::RESOLVED, ReportStatus::REJECTED], true)) {
            abort(422, '处理结果必须为 resolved 或 rejected');
        }

        // 默认把状态先推进到 processing，避免多人重复操作
        if ($report->status === ReportStatus::PENDING) {
            $report->forceFill(['status' => ReportStatus::PROCESSING])->save();
        }

        $payload = [
            'status' => $newStatus->value,
            'remark' => $request->validated()['remark'] ?? null,
            'actions' => $request->validated()['actions'] ?? [],
        ];

        $report = $this->reportService->process($report, $request->user('admin'), $payload);

        return new ReportResource($report);
    }

    /**
     * 举报列表导出
     */
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        return $this->exportDispatch(
            $request,
            ReportListExport::class,
            [$this->reportFilters($request)],
            $this->exportFileName('reports'),
            ReportListExport::SYNC_THRESHOLD,
        );
    }

    /**
     * 列表筛选参数汇总，供 index / export 共用
     *
     * @return array<string, mixed>
     */
    protected function reportFilters(Request $request): array
    {
        return [
            'keyword' => $request->query('keyword'),
            'status' => $request->query('status'),
            'category' => $request->query('category'),
            'reportable_type' => $request->query('reportable_type'),
            'handler_id' => $request->query('handler_id'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];
    }
}
