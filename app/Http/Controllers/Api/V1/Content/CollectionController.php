<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Content\Collection\StoreCollectionRequest;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Models\Content\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 收藏管理
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CollectionController extends Controller
{
    /**
     * CollectionController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Collection::class, 'collection');
    }

    /**
     * 获取收藏列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Collection::forUser($request->user()->id)
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->input('source_type')))
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return CollectionResource::collection($items);
    }

    /**
     * 添加收藏
     */
    public function store(StoreCollectionRequest $request): CollectionResource
    {
        return new CollectionResource(Collection::create($request->validated()));
    }

    /**
     * 取消收藏
     */
    public function destroy(Collection $collection): Response
    {
        $collection->delete();

        return response()->noContent();
    }
}
