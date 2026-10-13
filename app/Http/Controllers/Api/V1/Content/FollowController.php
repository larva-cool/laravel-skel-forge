<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Content\Follow\StoreFollowRequest;
use App\Http\Resources\Api\V1\FollowResource;
use App\Models\Content\Follow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 关注管理
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class FollowController extends Controller
{
    /**
     * FollowController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Follow::class, 'follow');
    }

    /**
     * 获取关注列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Follow::forUser($request->user()->id)
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->input('source_type')))
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return FollowResource::collection($items);
    }

    /**
     * 添加关注
     */
    public function store(StoreFollowRequest $request): FollowResource
    {
        return new FollowResource(Follow::create($request->validated()));
    }

    /**
     * 取消关注
     */
    public function destroy(Follow $follow): Response
    {
        $follow->delete();

        return response()->noContent();
    }
}
