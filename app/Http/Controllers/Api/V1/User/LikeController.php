<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Like\StoreLikeRequest;
use App\Http\Resources\Api\V1\LikeResource;
use App\Models\Content\Like;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 点赞管理
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class LikeController extends Controller
{
    /**
     * LikeController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Like::class, 'like');
    }

    /**
     * 获取点赞列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Like::forUser($request->user()->id)
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->input('source_type')))
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return LikeResource::collection($items);
    }

    /**
     * 添加点赞
     */
    public function store(StoreLikeRequest $request): LikeResource
    {
        return new LikeResource(Like::create($request->validated()));
    }

    /**
     * 取消点赞
     */
    public function destroy(Like $like): Response
    {
        $like->delete();

        return response()->noContent();
    }
}
