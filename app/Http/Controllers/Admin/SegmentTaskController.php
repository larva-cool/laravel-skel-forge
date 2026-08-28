<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserSegment\SegmentTaskIndexRequest;
use App\Http\Resources\Admin\SegmentTaskResource;
use App\Models\UserSegment\SegmentTask;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台分群异步任务查询控制器
 *
 * 提供当前管理员提交的分群异步任务列表与详情查询能力。
 * 任务由 BulkSegmentTaskJob 异步执行，提交端点位于 UserSegmentController。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentTaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        // 任务查询归到 segments.assign（提交任务的人必须能看自己提交的任务）
        $this->middleware('permission:segments.assign')->only(['index', 'show']);
    }

    /**
     * 当前管理员的分群异步任务列表（分页，支持 status / type 筛选）
     */
    public function index(SegmentTaskIndexRequest $request): AnonymousResourceCollection
    {
        $query = SegmentTask::query()
            ->where('admin_id', $request->user('admin')?->id ?? 0)
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return SegmentTaskResource::collection($query->paginate(per_page($request)));
    }

    /**
     * 分群异步任务详情
     */
    public function show(int $id): SegmentTaskResource
    {
        $task = SegmentTask::query()
            ->where('admin_id', request()->user('admin')?->id ?? 0)
            ->findOrFail($id);

        return new SegmentTaskResource($task);
    }
}
