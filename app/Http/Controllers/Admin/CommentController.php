<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Requests\Admin\Comment\CommentReviewRequest;
use App\Http\Resources\Admin\CommentResource;
use App\Models\Content\Comment;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台评论管理控制器（运营管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CommentController extends Controller
{
    use \App\Exports\HandlesExports;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:comments.index')->only(['index', 'show', 'replies']);
        $this->middleware('permission:comments.edit')->only(['review']);
        $this->middleware('permission:comments.delete')->only(['destroy']);
    }

    /**
     * 评论列表（分页，支持按状态/源类型/关键词过滤）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Comment::query()->with('user');

        // 按状态筛选
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // 按源类型筛选
        if ($sourceType = $request->query('source_type')) {
            $query->where('source_type', $sourceType);
        }

        // 关键词搜索：评论内容
        if ($keyword = $request->query('keyword')) {
            $query->where('content', 'like', "%{$keyword}%");
        }

        // 按用户 ID 筛选
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', (int) $userId);
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return CommentResource::collection($items);
    }

    /**
     * 导出评论列表
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        return $this->exportDispatch(
            $request,
            $this->commentExportClass(),
            [$this->commentListFilters($request)],
            $this->exportFileName('comments'),
            \App\Exports\Admin\CommentListExport::SYNC_THRESHOLD,
        );
    }

    /**
     * 评论详情（含上级评论摘要）
     */
    public function show(int $id): CommentResource
    {
        /** @var Comment $comment */
        $comment = Comment::query()->with('user')->findOrFail($id);

        // 若本条是对评论的回复，预加载上级评论及其作者
        if ($comment->source_type === 'comment') {
            $comment->load(['source' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Comment::class => ['user'],
            ])]);
        }

        return new CommentResource($comment);
    }

    /**
     * 回复列表（指定评论下的所有直接回复）
     */
    public function replies(Request $request, int $id): AnonymousResourceCollection
    {
        // 确认父评论存在
        Comment::query()->findOrFail($id);

        $items = Comment::query()
            ->with('user')
            ->where('source_type', 'comment')
            ->where('source_id', $id)
            ->orderBy('id')
            ->paginate(per_page($request));

        return CommentResource::collection($items);
    }

    /**
     * 审核评论（通过/驳回/设置审核中）
     */
    public function review(CommentReviewRequest $request, int $id): CommentResource
    {
        /** @var Comment $comment */
        $comment = Comment::query()->findOrFail($id);
        $data = $request->validated();

        $comment->update([
            'status' => ReviewStatus::from($data['status']),
        ]);

        $comment->load('user');

        return new CommentResource($comment);
    }

    /**
     * 删除评论
     */
    public function destroy(int $id): JsonResponse
    {
        $comment = Comment::query()->findOrFail($id);
        $comment->delete();

        return response()->json(status: 204);
    }
}
