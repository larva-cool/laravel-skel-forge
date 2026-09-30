<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\StoreReportRequest;
use App\Http\Resources\Api\V1\ReportResource;
use App\Models\Content\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 用户端举报接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 提交举报（C 端用户）
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $payload = $request->payload();
        $class = $payload['target_class'];

        if (! is_string($class) || ! class_exists($class)) {
            abort(422, '不支持的举报对象类型');
        }

        /** @var \Illuminate\Database\Eloquent\Model $target */
        $target = $class::query()->findOrFail((int) $request->input('target_id'));

        $report = $this->reportService->submit($request->user(), $target, [
            'category' => $payload['category'],
            'reason' => $payload['reason'],
            'evidence' => $payload['evidence'],
        ]);

        return ReportResource::make($report)->response()->setStatusCode(201);
    }

    /**
     * 我的举报列表（当前用户提交的所有举报）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Report::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return ReportResource::collection($items);
    }
}
