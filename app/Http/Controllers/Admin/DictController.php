<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CacheKey;
use App\Http\Requests\Admin\Dict\DictSaveRequest;
use App\Http\Resources\Admin\DictItemResource;
use App\Http\Resources\Admin\DictResource;
use App\Models\System\Dict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 后台字典管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DictController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:dicts.index')->only(['index', 'show']);
        $this->middleware('permission:dicts.create')->only(['store']);
        $this->middleware('permission:dicts.edit')->only(['update']);
        $this->middleware('permission:dicts.delete')->only(['destroy']);
    }

    /**
     * 字典列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Dict::query();

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderBy('sort')->orderByDesc('id')->paginate($perPage);

        return DictResource::collection($items);
    }

    /**
     * 创建字典
     */
    public function store(DictSaveRequest $request): JsonResponse
    {
        $dict = Dict::create($request->validated());

        return response()->json(new DictResource($dict), 201);
    }

    /**
     * 获取字典详情
     */
    public function show(int $id): DictResource
    {
        return new DictResource(Dict::findOrFail($id));
    }

    /**
     * 更新字典
     */
    public function update(DictSaveRequest $request, int $id): DictResource
    {
        $dict = Dict::findOrFail($id);
        $dict->update($request->validated());

        return new DictResource($dict);
    }

    /**
     * 删除字典
     */
    public function destroy(int $id): JsonResponse
    {
        $dict = Dict::findOrFail($id);
        $dict->delete();

        // 清除该字典缓存
        Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, $dict->code));

        return response()->json(status: 204);
    }

    /**
     * 按字典编码获取启用的字典项（用于前端下拉选项/标签渲染，带缓存）
     */
    public function itemsByCode(string $code): AnonymousResourceCollection
    {
        $cacheKey = CacheKey::key(CacheKey::DICT_TYPE, $code);

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\System\DictItem>|null $items */
        $items = Cache::remember($cacheKey, Carbon::now()->addHours(2), function () use ($code) {
            $dict = Dict::query()
                ->where('code', $code)
                ->where('status', 1)
                ->first();

            if (! $dict) {
                return collect();
            }

            return $dict->dictItems()
                ->where('status', 1)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
        });

        return DictItemResource::collection($items);
    }
}
