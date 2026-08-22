<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DictItem\DictItemSaveRequest;
use App\Http\Resources\Admin\DictItemResource;
use App\Models\System\DictItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台字典数据管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DictItemController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:dict-items.index')->only(['index', 'show']);
        $this->middleware('permission:dict-items.create')->only(['store']);
        $this->middleware('permission:dict-items.edit')->only(['update']);
        $this->middleware('permission:dict-items.delete')->only(['destroy']);
    }

    /**
     * 字典数据列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = DictItem::query();

        if ($dictId = $request->query('dict_id')) {
            $query->where('dict_id', $dictId);
        }

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('label', 'like', "%{$keyword}%")
                    ->orWhere('value', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderBy('sort')->orderByDesc('id')->paginate($perPage);

        return DictItemResource::collection($items);
    }

    /**
     * 创建字典数据
     */
    public function store(DictItemSaveRequest $request): JsonResponse
    {
        $dictItem = DictItem::create($request->validated());

        return response()->json(new DictItemResource($dictItem), 201);
    }

    /**
     * 获取字典数据详情
     */
    public function show(int $id): DictItemResource
    {
        return new DictItemResource(DictItem::findOrFail($id));
    }

    /**
     * 更新字典数据
     */
    public function update(DictItemSaveRequest $request, int $id): DictItemResource
    {
        $dictItem = DictItem::findOrFail($id);
        $dictItem->update($request->validated());

        return new DictItemResource($dictItem);
    }

    /**
     * 删除字典数据
     */
    public function destroy(int $id): JsonResponse
    {
        $dictItem = DictItem::findOrFail($id);
        $dictItem->delete();

        return response()->json(status: 204);
    }
}
