<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Feedback\FeedbackUpdateRequest;
use App\Http\Resources\Admin\FeedbackResource;
use App\Http\Resources\Admin\FeedbackStatResource;
use App\Models\System\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台用户反馈管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackController extends Controller
{
    use \App\Exports\HandlesExports;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:feedbacks.index')->only(['index', 'show', 'stats', 'export']);
        $this->middleware('permission:feedbacks.edit')->only(['update']);
        $this->middleware('permission:feedbacks.delete')->only(['destroy']);
    }

    /**
     * 反馈列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Feedback::query()->with('admin');

        // 关键词搜索：编号/标题/描述/联系方式
        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $like = "%{$keyword}%";
                $q->where('code', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('content', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_info', 'like', $like);
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return FeedbackResource::collection($items);
    }

    /**
     * 反馈统计数据
     */
    public function stats(): FeedbackStatResource
    {
        return new FeedbackStatResource(FeedbackStatResource::makeStats());
    }

    /**
     * 获取反馈详情
     */
    public function show(int $id): FeedbackResource
    {
        $feedback = Feedback::query()->with(['admin', 'user'])->findOrFail($id);

        return new FeedbackResource($feedback);
    }

    /**
     * 更新反馈（处理反馈：状态/优先级/备注）
     */
    public function update(FeedbackUpdateRequest $request, int $id): FeedbackResource
    {
        $feedback = Feedback::query()->findOrFail($id);
        $data = $request->validated();

        // 自动记录处理人
        if (isset($data['status']) && $feedback->admin_id === null) {
            $data['admin_id'] = $request->user('admin')?->id;
        }

        $feedback->update($data);
        $feedback->load('admin');

        return new FeedbackResource($feedback);
    }

    /**
     * 删除反馈
     */
    public function destroy(int $id): JsonResponse
    {
        $feedback = Feedback::query()->findOrFail($id);
        $feedback->delete();

        return response()->json(status: 204);
    }

    /**
     * 导出反馈列表
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        return $this->exportDispatch(
            $request,
            $this->feedbackExportClass(),
            [$this->feedbackListFilters($request)],
            $this->exportFileName('feedbacks'),
            \App\Exports\Admin\FeedbackListExport::SYNC_THRESHOLD,
        );
    }
}
