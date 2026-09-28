<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AgreementStatus;
use App\Http\Requests\Admin\Agreement\AgreementIndexRequest;
use App\Http\Requests\Admin\Agreement\AgreementSaveRequest;
use App\Http\Resources\Admin\AgreementResource;
use App\Models\Operation\Agreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台协议管理控制器（运营管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:agreements.index')->only(['index', 'show']);
        $this->middleware('permission:agreements.create')->only(['store']);
        $this->middleware('permission:agreements.edit')->only(['update', 'toggleStatus']);
        $this->middleware('permission:agreements.delete')->only(['destroy']);
    }

    /**
     * 协议列表（分页，支持关键词/类别/状态筛选）
     */
    public function index(AgreementIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Agreement::query();

        // 关键词搜索：标题
        if ($keyword = $request->query('keyword')) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        // 类别筛选
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        // 状态筛选
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return AgreementResource::collection($items);
    }

    /**
     * 创建协议
     */
    public function store(AgreementSaveRequest $request): AgreementResource
    {
        $agreement = Agreement::query()->create($request->validated());

        return new AgreementResource($agreement);
    }

    /**
     * 协议详情
     */
    public function show(int $id): AgreementResource
    {
        $agreement = Agreement::query()->with('admin')->findOrFail($id);

        return new AgreementResource($agreement);
    }

    /**
     * 更新协议
     */
    public function update(AgreementSaveRequest $request, int $id): AgreementResource
    {
        $agreement = Agreement::query()->findOrFail($id);
        $agreement->update($request->validated());

        return new AgreementResource($agreement->fresh());
    }

    /**
     * 切换协议状态（草稿→发布 / 发布→归档 / 归档→发布）
     */
    public function toggleStatus(int $id): AgreementResource
    {
        $agreement = Agreement::query()->findOrFail($id);

        $newStatus = match ($agreement->status) {
            AgreementStatus::DRAFT => AgreementStatus::PUBLISHED,
            AgreementStatus::PUBLISHED => AgreementStatus::ARCHIVED,
            AgreementStatus::ARCHIVED => AgreementStatus::PUBLISHED,
        };

        $agreement->update([
            'status' => $newStatus,
            'effective_at' => $newStatus === AgreementStatus::PUBLISHED && $agreement->effective_at === null
                ? now()
                : $agreement->effective_at,
        ]);

        return new AgreementResource($agreement->fresh());
    }

    /**
     * 删除协议
     */
    public function destroy(int $id): JsonResponse
    {
        $agreement = Agreement::query()->findOrFail($id);
        $agreement->delete();

        return response()->json(status: 204);
    }
}
