<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Content\Comment\StoreCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Content\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 评论管理
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'destroy']);
        $this->authorizeResource(Comment::class, 'comment');
    }

    /**
     * 获取评论列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Comment::query()
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->input('source_type')))
            ->when($request->filled('source_id'), fn ($query) => $query->where('source_id', $request->input('source_id')))
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return CommentResource::collection($items);
    }

    /**
     * 发布评论
     */
    public function store(StoreCommentRequest $request): CommentResource
    {
        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;
        unset($data['parent_id']);

        $comment = Comment::create($data);

        // 若是回复（parent_id 指定了一个评论），挂到父评论的 comments 多态关系下
        if ($parentId) {
            $parent = Comment::find($parentId);
            if ($parent) {
                $comment->source_type = Comment::class;
                $comment->source_id = $parent->id;
                $comment->save();
            }
        }

        return new CommentResource($comment);
    }

    /**
     * 删除评论
     */
    public function destroy(Comment $comment): Response
    {
        $comment->delete();

        return response()->noContent();
    }
}
